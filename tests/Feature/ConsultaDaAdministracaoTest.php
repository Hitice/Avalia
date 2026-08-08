<?php

use App\Actions\Consumo\ExecutarDemonstracao;
use App\Models\Consulta;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A consulta que a administracao faz a trabalho.
 *
 * Mesmo mecanismo da demonstracao do vendedor, regra de dinheiro diferente:
 * ninguem e cobrado, o custo do fornecedor entra no custo do periodo e nao ha
 * comissao de onde descontar. O risco que estes testes seguram e o custo da
 * casa virar desconto na comissao de um vendedor no fechamento.
 */
it('consulta pela administracao com preco zero e custo da casa', function () {
    carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $chefe = Staff::factory()->admin()->create();

    comoVendedor($chefe)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id,
        'documento' => '12345678901',
    ])->assertRedirect();

    $consulta = Consulta::sole();

    expect($consulta->cliente_id)->toBeNull()
        ->and($consulta->vendedor_id)->toBe($chefe->id)
        ->and($consulta->preco_cents)->toBe(0)
        ->and($consulta->custo_cents)->toBe(150)
        ->and($consulta->finalidade)->toBe(ExecutarDemonstracao::FINALIDADE_OPERACAO);
});

it('nao desconta comissao de ninguem pela consulta da administracao', function () {
    [$vendedor, $empresa] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $chefe = Staff::factory()->admin()->create();

    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 10);
    $fatura = app(App\Actions\Consumo\FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];
    app(App\Actions\Financeiro\RegistrarLiquidacao::class)($fatura);

    comoVendedor($chefe)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => '11111111111',
    ]);
    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => '22222222222',
    ]);

    $resposta = comoVendedor($chefe)->get(route('painel'))->assertOk();
    $comissoes = collect($resposta->viewData('comissaoPorVendedor'));

    // O vendedor desconta a demonstracao dele. O chefe nem aparece na lista de
    // repasse: consulta da casa nao vira desconto de comissao de ninguem.
    expect($comissoes->firstWhere('id', $vendedor->id)['demonstracoes'])->toBe(150)
        ->and($comissoes->firstWhere('id', $chefe->id))->toBeNull();

    // E o custo das duas continua visivel como custo do periodo.
    expect($resposta->viewData('custoProprioCents'))->toBe(300);
});

it('da ao admin um teto diario maior que o do vendedor', function () {
    $chefe = Staff::factory()->admin()->create();
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    expect(ExecutarDemonstracao::teto($chefe))->toBe(Consulta::LIMITE_DIARIO_OPERACAO)
        ->and(ExecutarDemonstracao::teto($vendedor))->toBe(Consulta::LIMITE_DIARIO_DEMONSTRACAO)
        ->and(Consulta::LIMITE_DIARIO_OPERACAO)->toBeGreaterThan(Consulta::LIMITE_DIARIO_DEMONSTRACAO);
});

it('leva o admin a tela de consulta pelo menu, com o texto da operacao', function () {
    carteira();

    admin()->get(route('carteira.consultar'))
        ->assertOk()
        ->assertSee('Consulta da operação', false)
        ->assertDontSee('sai da sua comissão', false);

    // O item do menu nao pode voltar a ser exclusivo do vendedor.
    expect(collect(App\Helpers\MenuHelper::getMainNavItems())
        ->firstWhere('path', '/carteira/consultar')['papeis'] ?? null)->toBeNull();
});
