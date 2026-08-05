<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Plano;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Entra como um vendedor especifico. */
function comoVendedor(Staff $vendedor): Tests\TestCase
{
    return test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => $vendedor->sessao_versao]);
}

/** Vendedor com uma empresa na carteira, plano e servico precificado. */
function carteira(): array
{
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    $plano = Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Vendedor da Casa']);

    $empresa = Cliente::factory()->create([
        'razao_social' => 'Empresa da Carteira LTDA',
        'plano_id' => $plano->id,
        'vendedor_id' => $vendedor->id,
    ]);

    return [$vendedor, $empresa, Servico::firstWhere('codigo', 'scpc-bvs')];
}

/*
|--------------------------------------------------------------------------
| O que o vendedor ve
|--------------------------------------------------------------------------
*/

it('mostra ao vendedor as empresas da carteira dele', function () {
    [$vendedor, $empresa] = carteira();

    comoVendedor($vendedor)->get(route('carteira'))
        ->assertOk()
        ->assertSee('Empresa da Carteira LTDA');
});

it('nao mostra empresa de outra carteira', function () {
    // Nao ha parametro de rota que escolha a carteira: e sempre a de quem
    // esta logado, entao nao ha URL que peca a de outro.
    [$vendedor] = carteira();
    Cliente::factory()->create([
        'razao_social' => 'Empresa de Outro LTDA',
        'vendedor_id' => Staff::factory()->create(['papel' => 'vendedor'])->id,
    ]);

    comoVendedor($vendedor)->get(route('carteira'))
        ->assertOk()
        ->assertDontSee('Empresa de Outro LTDA');
});

it('nao leva custo, lucro nem margem para a tela do vendedor', function () {
    // Sao numeros internos: a carteira e tela separada justamente para nao
    // depender de um `@if` para nao vazar.
    [$vendedor, $empresa, $servico] = carteira();

    app(RegistrarConsulta::class)($empresa, $servico, 10);
    app(FecharCompetencia::class)($empresa, '2026-07');

    $html = comoVendedor($vendedor)->get(route('carteira'))->assertOk()->getContent();

    expect($html)->not->toContain('Custo')
        ->not->toContain('Lucro')
        ->not->toContain('Margem');
});

it('soma na comissao liberada so o que ja foi pago', function () {
    [$vendedor, $empresa] = carteira();

    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    $resposta = comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    expect($resposta->viewData('aReceber'))->toBe(0)
        ->and($resposta->viewData('aConfirmar'))->toBe($fatura->comissao_cents);

    app(RegistrarLiquidacao::class)($fatura);

    $depois = comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    expect($depois->viewData('aReceber'))->toBe($fatura->comissao_cents)
        ->and($depois->viewData('aConfirmar'))->toBe(0);
});

it('mostra o consumo do mes que ainda nao fechou', function () {
    [$vendedor, $empresa, $servico] = carteira();

    app(RegistrarConsulta::class)($empresa, $servico, 5);   // 5 x R$ 3,24

    comoVendedor($vendedor)->get(route('carteira'))
        ->assertOk()
        ->assertSee("R$\u{00A0}16,20", false);
});

/*
|--------------------------------------------------------------------------
| O que ele continua sem poder abrir
|--------------------------------------------------------------------------
*/

it('nao abre catalogo, empresas nem financeiro pela carteira', function () {
    [$vendedor] = carteira();

    foreach (['catalogo.tabela', 'empresas.index', 'financeiro.index', 'auditoria'] as $rota) {
        comoVendedor($vendedor)->get(route($rota))->assertForbidden();
    }
});

it('poe a carteira no menu do vendedor e esconde do admin', function () {
    [$vendedor] = carteira();

    comoVendedor($vendedor)->get('/')->assertOk()->assertSee('href="/carteira"', false);

    admin()->get('/')->assertOk()->assertDontSee('href="/carteira"', false);
});
