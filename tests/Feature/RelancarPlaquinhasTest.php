<?php

use App\Models\ContaFinanceira;
use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Models\PartidaFinanceira;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Uma venda lancada, com a comissao rebaixada para a regra antiga (sobre o liquido). */
function vendaNaRegraAntiga(): array
{
    $emails = config('etiquetas.socios');
    Staff::factory()->admin()->create(['email' => $emails[0]]);
    Staff::factory()->admin()->create(['email' => $emails[1]]);
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    $placa = Etiqueta::factory()->ativa()->create(['vendedor_id' => $warley->id]);
    test()->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    $valor = (int) config('etiquetas.precos.placa_cents');
    $custo = (int) config('etiquetas.custo_cents');
    $pct = (int) config('etiquetas.comissao_pct');
    $nova = (int) round($valor * $pct / 100);
    $antiga = (int) round(($valor - $custo) * $pct / 100);

    $lanc = LancamentoFinanceiro::where('origem_tipo', 'etiqueta')->where('origem_id', $placa->id)->sole();
    $idComissao = ContaFinanceira::firstWhere('codigo', 'comissao')->id;
    $idAPagar = ContaFinanceira::firstWhere('codigo', 'comissao-a-pagar')->id;

    // Mesma diferenca nas duas pernas, para a soma continuar zero.
    PartidaFinanceira::where('lancamento_id', $lanc->id)->where('conta_id', $idComissao)->update(['valor_cents' => $antiga]);
    PartidaFinanceira::where('lancamento_id', $lanc->id)->where('conta_id', $idAPagar)->update(['valor_cents' => -$antiga]);

    return [$placa, $antiga, $nova];
}

it('estorna e relanca a venda cuja comissao esta na regra antiga', function () {
    [, $antiga, $nova] = vendaNaRegraAntiga();
    expect(saldo('comissao'))->toBe($antiga);

    test()->artisan('avalia:relancar-plaquinhas')->assertSuccessful();

    // Original + estorno (zeram) + relancamento: tres lancamentos, saldo novo.
    expect(saldo('comissao'))->toBe($nova)
        ->and(saldo('comissao-a-pagar'))->toBe($nova)
        ->and(LancamentoFinanceiro::count())->toBe(3)
        ->and((int) DB::table('partidas_financeiras')->sum('valor_cents'))->toBe(0);
});

it('nao relanca de novo o que ja relancou, nem o que ja bate', function () {
    [, , $nova] = vendaNaRegraAntiga();

    test()->artisan('avalia:relancar-plaquinhas')->assertSuccessful();
    test()->artisan('avalia:relancar-plaquinhas')->assertSuccessful();

    expect(LancamentoFinanceiro::count())->toBe(3)
        ->and(saldo('comissao'))->toBe($nova);
});

it('simula sem gravar nada', function () {
    [, $antiga] = vendaNaRegraAntiga();

    test()->artisan('avalia:relancar-plaquinhas --simular')->assertSuccessful();

    expect(LancamentoFinanceiro::count())->toBe(1)
        ->and(saldo('comissao'))->toBe($antiga);
});
