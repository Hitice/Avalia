<?php

use App\Models\AceiteDocumento;
use App\Models\DocumentoLegal;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Publico por documento e o termo do vendedor.
 *
 * A administracao define quem cada documento alcanca (empresa, operador,
 * vendedor) e se exige aceite ou e leitura e apoio. Cada publico so responde
 * pelos proprios termos: documento de vendedor nao trava empresa, e
 * vice-versa.
 */
function termoDoVendedor(array $extra = []): DocumentoLegal
{
    return DocumentoLegal::create($extra + [
        'tipo' => 'conduta-vendas',
        'versao' => '1.0',
        'titulo' => 'Termo de conduta em vendas',
        'conteudo' => "## Conduta\n\nO vendedor demonstra apenas com autorização do interessado.",
        'exige_aceite' => true,
        'ativo' => true,
        'para_empresa' => false,
        'para_operador' => false,
        'para_vendedor' => true,
    ]);
}

it('publica documento com publico definido e apoio sem aceite', function () {
    admin()->post(route('documentos.salvar'), [
        'titulo' => 'Guia de boas práticas',
        'tipo' => 'guia-boas-praticas',
        'versao' => '2026.08',
        'conteudo' => 'Material de apoio para consultas responsáveis.',
        'para_vendedor' => '1',
    ])->assertSessionDoesntHaveErrors();

    $documento = DocumentoLegal::firstWhere('tipo', 'guia-boas-praticas');

    expect($documento->para_vendedor)->toBeTrue()
        ->and($documento->para_empresa)->toBeFalse()
        ->and($documento->exige_aceite)->toBeFalse();

    // Apoio aparece na tela do vendedor sem formulario de aceite.
    [$vendedor] = carteira();
    comoVendedor($vendedor)->get(route('termos'))->assertOk()
        ->assertSee('Leitura e apoio')
        ->assertSee('Guia de boas práticas');
});

it('poe o vendedor nos termos ao entrar e trava a demonstracao ate o aceite', function () {
    $termo = termoDoVendedor();
    [$vendedor] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    // A entrada cai nos termos.
    $this->post('/entrar', ['email' => $vendedor->email, 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('termos'));

    // Demonstrar antes do aceite e recusado.
    comoVendedor($vendedor)->from(route('carteira.consultar'))
        ->post(route('carteira.consultar.executar'), [
            'servico_id' => $servico->id, 'documento' => '12345678901',
        ])->assertSessionHas('erro');

    // Aceita com a mesma robustez da empresa: nome, li e hash.
    comoVendedor($vendedor)->post(route('termos.aceitar', $termo), [
        'responsavel' => 'Vendedor da Casa',
        'li' => '1',
        'hash' => $termo->hashConteudo(),
    ])->assertSessionHas('ok');

    expect(AceiteDocumento::where('staff_id', $vendedor->id)->count())->toBe(1);

    // Destravado: entra no painel e demonstra.
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->post('/entrar', ['email' => $vendedor->email, 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('painel'));

    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => '12345678901',
    ])->assertRedirect();
});

it('nao trava empresa nem operador com termo que e so do vendedor', function () {
    termoDoVendedor();
    $empresa = empresaComPlano();
    $operador = App\Models\Operador::factory()->create(['cliente_id' => $empresa->id]);

    expect($empresa->documentosObrigatoriosAceitos())->toBeTrue()
        ->and($operador->aceitouObrigatorios())->toBeTrue();
});

it('nao trava o administrador com termo de vendedor', function () {
    termoDoVendedor();

    // Quem publica os termos nao pode ficar trancado por eles.
    admin()->get(route('painel'))->assertOk();
});

it('recusa aceite de termo do vendedor com hash vencido', function () {
    $termo = termoDoVendedor();
    [$vendedor] = carteira();

    $hashAntigo = $termo->hashConteudo();
    $termo->update(['conteudo' => $termo->conteudo."\n\nCláusula nova."]);

    comoVendedor($vendedor)->post(route('termos.aceitar', $termo), [
        'responsavel' => 'Vendedor da Casa',
        'li' => '1',
        'hash' => $hashAntigo,
    ])->assertSessionHas('erro');

    expect(AceiteDocumento::count())->toBe(0);
});
