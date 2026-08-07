<?php

use App\Mail\ConviteDeAcesso;
use App\Models\AceiteDocumento;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Operador;
use App\Models\Servico;
use App\Support\Convite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Operadores: cada pessoa que consulta tem conta, historico e aceite proprios.
 *
 * O que se protege: a resposta de LGPD para "quem consultou este documento"
 * e uma pessoa com nome, o aceite de um operador nao substitui o contratual
 * da empresa, e desativar um operador o derruba na hora sem tocar nos outros.
 */
function documentoObrigatorio(): DocumentoLegal
{
    return DocumentoLegal::create([
        'tipo' => 'termos',
        'versao' => '1.0',
        'titulo' => 'Termos de uso das consultas',
        'conteudo' => "## Finalidade\n\nConsultas destinam-se à análise de crédito em negócios próprios.",
        'exige_aceite' => true,
        'ativo' => true,
    ]);
}

it('deixa o admin criar operador e envia o convite de acesso', function () {
    Mail::fake();
    $empresa = empresaComPlano();

    admin()->post(route('empresas.operadores.salvar', $empresa), [
        'nome' => 'Joana Atendente',
        'email' => 'joana@empresa.com.br',
    ])->assertSessionHas('ok');

    $operador = Operador::sole();

    expect($operador->cliente_id)->toBe($empresa->id)
        ->and($operador->ativo)->toBeTrue();

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo('joana@empresa.com.br'));
});

it('nao deixa o vendedor criar operador', function () {
    [$vendedor, $empresa] = carteira();

    comoVendedor($vendedor)->post(route('empresas.operadores.salvar', $empresa), [
        'nome' => 'Fulana Qualquer',
        'email' => 'fulana@empresa.com.br',
    ])->assertForbidden();

    expect(Operador::count())->toBe(0);
});

it('recusa e-mail que ja tem acesso em qualquer conta', function () {
    $empresa = empresaComPlano();

    admin()->post(route('empresas.operadores.salvar', $empresa), [
        'nome' => 'Nome Valido Aqui',
        'email' => $empresa->email,
    ])->assertSessionHasErrors('email');
});

it('deixa o operador definir a propria senha pelo link do convite', function () {
    $operador = Operador::factory()->create();
    $link = Convite::link($operador, 'operador');

    $this->post($link, ['senha' => 'senha-nova-do-operador', 'senha_confirmation' => 'senha-nova-do-operador'])
        ->assertRedirect(route('entrar'));

    expect(Hash::check('senha-nova-do-operador', $operador->fresh()->senha))->toBeTrue();
});

it('poe o operador na area da empresa dele, comecando pelos termos', function () {
    $empresa = empresaComPlano();
    $documento = documentoObrigatorio();
    $operador = Operador::factory()->create(['cliente_id' => $empresa->id, 'email' => 'op@empresa.com.br']);

    // Sem a propria ciencia dos termos, a entrada cai nos documentos.
    $this->post('/entrar', ['email' => 'op@empresa.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.documentos'));

    expect(session('operador_id'))->toBe($operador->id);

    // O aceite do operador e dele: nao vira o aceite contratual da empresa.
    $this->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

    expect(AceiteDocumento::where('operador_id', $operador->id)->count())->toBe(1)
        ->and($empresa->fresh()->documentosObrigatoriosAceitos())->toBeFalse();
});

it('grava na consulta quem clicou, com nome', function () {
    $empresa = empresaComPlano();
    $operador = Operador::factory()->create([
        'cliente_id' => $empresa->id,
        'nome' => 'Joana Atendente',
        'email' => 'op@empresa.com.br',
    ]);

    $this->post('/entrar', ['email' => 'op@empresa.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.painel'));

    $this->post(route('empresa.consultas.executar'), [
        'servico_id' => Servico::firstWhere('codigo', 'scpc-bvs')->id,
        'documento' => '12345678901',
        'finalidade' => 'Análise de crédito para venda a prazo',
    ]);

    $consulta = Consulta::sole();

    expect($consulta->operador_id)->toBe($operador->id)
        ->and($consulta->solicitante)->toBe('Joana Atendente');
});

it('derruba a sessao do operador desativado sem tocar na conta master', function () {
    $empresa = empresaComPlano();
    $operador = Operador::factory()->create(['cliente_id' => $empresa->id, 'email' => 'op@empresa.com.br']);

    $this->post('/entrar', ['email' => 'op@empresa.com.br', 'senha' => 'senha-valida-123']);
    $this->get(route('empresa.painel'))->assertOk();

    admin()->post(route('empresas.operadores.alternar', [$empresa, $operador]));
    expect($operador->fresh()->ativo)->toBeFalse();

    // A sessao aberta morre na requisicao seguinte...
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->post('/entrar', ['email' => 'op@empresa.com.br', 'senha' => 'senha-valida-123'])
        ->assertSessionHasErrors('email');

    // ...e a conta master continua entrando normalmente.
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->post('/entrar', ['email' => $empresa->email, 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.painel'));
});

it('recupera a senha do operador pelo esqueci minha senha', function () {
    Mail::fake();
    $operador = Operador::factory()->create(['email' => 'op@empresa.com.br']);

    $this->post(route('senha.esqueci.enviar'), ['email' => 'op@empresa.com.br'])
        ->assertSessionHas('ok');

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo('op@empresa.com.br'));
});
