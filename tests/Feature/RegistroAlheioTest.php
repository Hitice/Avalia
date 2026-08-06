<?php

use App\Actions\Consumo\ExecutarConsulta;
use App\Actions\Consumo\FecharCompetencia;
use App\Models\Cliente;
use App\Models\DocumentoLegal;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Ninguem alcanca o registro de outro trocando o numero na URL
|--------------------------------------------------------------------------
|
| A falha mais comum de aplicacao com login e esta: a tela lista so o que e
| seu, mas a rota aceita qualquer identificador. Quem descobre trocando o
| numero na barra de endereco le dado de outro cliente sem invadir nada.
|
*/

it('nao deixa a empresa abrir consulta de outra', function () {
    $minha = empresaComPlano();
    $alheia = empresaComPlano();

    $consulta = app(ExecutarConsulta::class)(
        $alheia, Servico::firstWhere('codigo', 'scpc-bvs'), '11144477735', 'Análise',
    )['consulta'];

    comoEmpresa($minha)->get(route('empresa.consultas.ver', $consulta))->assertForbidden();
});

it('nao deixa a empresa aceitar documento em nome de outra', function () {
    // O aceite e prova juridica: registrado no nome errado, nao vale para
    // nenhuma das duas.
    $minha = empresaComPlano();
    $alheia = empresaComPlano();

    $documento = DocumentoLegal::create([
        'titulo' => 'Contrato', 'tipo' => 'contrato', 'versao' => '1.0',
        'conteudo' => 'Conteúdo.', 'exige_aceite' => true, 'ativo' => true,
    ]);

    comoEmpresa($minha)->post(route('empresa.documentos.aceitar', $documento))->assertRedirect();

    expect($alheia->aceitesDocumentos()->count())->toBe(0)
        ->and($minha->aceitesDocumentos()->count())->toBe(1);
});

it('nao deixa o vendedor abrir a ficha de empresa nenhuma', function () {
    // A ficha mostra custo, imposto e lucro por fatura, e nao e da carteira:
    // e da administracao, mesmo quando a empresa e do vendedor.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $minha = Cliente::factory()->create(['vendedor_id' => $vendedor->id]);

    comoVendedor($vendedor)->get(route('empresas.ficha', $minha))->assertForbidden();
});

it('nao deixa o vendedor editar nem remover empresa de outra carteira', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $alheia = Cliente::factory()->create([
        'vendedor_id' => Staff::factory()->create(['papel' => 'vendedor'])->id,
    ]);

    comoVendedor($vendedor)->get(route('empresas.editar', $alheia))->assertForbidden();
    comoVendedor($vendedor)->put(route('empresas.atualizar', $alheia), [
        'razao_social' => 'Sequestrada LTDA',
        'cnpj' => '11111111000191',
        'email' => $alheia->email,
        'situacao' => 'ativo',
    ])->assertForbidden();
    comoVendedor($vendedor)->delete(route('empresas.remover', $alheia))->assertForbidden();

    expect($alheia->fresh()->razao_social)->not->toBe('Sequestrada LTDA');
});

it('nao deixa a empresa alcancar fatura pela area dela', function () {
    // O portal mostra as faturas da propria empresa a partir da sessao, e nao
    // ha rota que receba identificador de fatura do lado do cliente.
    $minha = empresaComPlano();
    $alheia = empresaComPlano();

    app(FecharCompetencia::class)($alheia, '2026-07');

    $faturas = comoEmpresa($minha)->get(route('empresa.faturas'))->viewData('faturas');

    expect($faturas)->toBeEmpty();
});

it('nao deixa administrador sem permissao financeira liquidar fatura alheia', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    $semPermissao = Staff::factory()->admin()->create(['pode_financeiro' => false]);

    test()->actingAs($semPermissao, 'staff')
        ->withSession(['versao_staff' => $semPermissao->sessao_versao])
        ->post(route('financeiro.liquidar', $fatura), ['motivo' => 'Confirmado por telefone com o cliente'])
        ->assertForbidden();

    expect($fatura->fresh()->estaLiquidada())->toBeFalse();
});

it('nao deixa conta desativada seguir usando o cookie', function () {
    // Cookie valido nao e o mesmo que acesso permitido: e o que separa
    // desligar alguem de esperar o cookie dele expirar.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    $vendedor->update(['ativo' => false]);

    comoVendedor($vendedor)->get(route('carteira'))->assertRedirect(route('entrar'));
});

it('nao deixa empresa encerrada seguir consultando', function () {
    $empresa = empresaComPlano();

    comoEmpresa($empresa)->get(route('empresa.painel'))->assertOk();

    $empresa->update(['situacao' => 'inativo']);

    comoEmpresa($empresa)->get(route('empresa.painel'))->assertRedirect(route('entrar'));
});
