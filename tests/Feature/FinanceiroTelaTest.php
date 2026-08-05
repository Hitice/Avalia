<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Auditoria;
use App\Models\Fatura;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Fecha uma competencia e devolve a fatura, reusando o cenario do fluxo. */
function faturaFechada(): Fatura
{
    [$cliente] = contrato();

    return app(FecharCompetencia::class)($cliente, '2026-07')['fatura'];
}

/*
|--------------------------------------------------------------------------
| Financeiro
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor abrir o financeiro', function () {
    // A tela mostra lucro e custo de cada fatura, que sao internos.
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('financeiro.index'))
        ->assertForbidden();
});

it('lista as faturas de todas as empresas com o que ha a receber', function () {
    $fatura = faturaFechada();

    admin()->get(route('financeiro.index'))
        ->assertOk()
        ->assertSee($fatura->cliente->razao_social)
        ->assertSee('07/2026')
        ->assertSee($fatura->totalRotulo(), false);
});

it('da baixa na fatura e libera a comissao', function () {
    // Cliente que nao pagou nao gera comissao: o vendedor aguarda a liquidacao.
    $fatura = faturaFechada();

    expect($fatura->comissao_liberada_em)->toBeNull();

    admin()->post(route('financeiro.liquidar', $fatura))->assertSessionHas('ok');

    $fatura->refresh();

    expect($fatura->estaLiquidada())->toBeTrue()
        ->and($fatura->liquidada_em)->not->toBeNull()
        ->and($fatura->comissao_liberada_em)->not->toBeNull();
});

it('nao da baixa duas vezes na mesma fatura', function () {
    $fatura = faturaFechada();

    admin()->post(route('financeiro.liquidar', $fatura));
    $liquidadaEm = $fatura->fresh()->liquidada_em;

    admin()->post(route('financeiro.liquidar', $fatura))->assertSessionHas('erro');

    expect($fatura->fresh()->liquidada_em->timestamp)->toBe($liquidadaEm->timestamp);
});

it('so soma no repasse a comissao de fatura ja paga', function () {
    $fatura = faturaFechada();

    // Em aberto, o vendedor ainda nao aparece no resumo.
    $resposta = admin()->get(route('financeiro.index'))->assertOk();
    expect($resposta->viewData('comissoes'))->toBeEmpty();

    admin()->post(route('financeiro.liquidar', $fatura));

    $comissoes = admin()->get(route('financeiro.index'))->viewData('comissoes');

    expect($comissoes)->toHaveCount(1)
        ->and($comissoes->first()->total_cents)->toBe($fatura->fresh()->comissao_cents);
});

it('filtra por situacao de pagamento', function () {
    $fatura = faturaFechada();

    admin()->get(route('financeiro.index', ['situacao' => 'liquidado']))
        ->assertOk()
        ->assertDontSee($fatura->cliente->razao_social);

    admin()->post(route('financeiro.liquidar', $fatura));

    admin()->get(route('financeiro.index', ['situacao' => 'liquidado']))
        ->assertOk()
        ->assertSee($fatura->cliente->razao_social);
});

/*
|--------------------------------------------------------------------------
| Auditoria
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor abrir a auditoria', function () {
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('auditoria'))
        ->assertForbidden();
});

it('mostra na trilha a baixa que acabou de acontecer', function () {
    $fatura = faturaFechada();

    admin()->post(route('financeiro.liquidar', $fatura));

    expect(Auditoria::where('acao', 'fatura.liquidada')->count())->toBe(1);

    admin()->get(route('auditoria'))
        ->assertOk()
        ->assertSee('fatura.liquidada');
});

it('filtra a trilha por acao', function () {
    $fatura = faturaFechada();
    admin()->post(route('financeiro.liquidar', $fatura));

    admin()->get(route('auditoria', ['acao' => 'fatura.liquidada']))
        ->assertOk()
        ->assertSee('fatura.liquidada');

    $vazia = admin()->get(route('auditoria', ['acao' => 'acao.que.nao.existe']))->assertOk();

    expect($vazia->viewData('registros'))->toBeEmpty();
});
