<?php

use App\Enums\NaturezaLancamento;
use App\Models\LancamentoFinanceiro;
use App\Models\Socio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('apaga os aportes e so eles, com rastro', function () {
    $socio = Socio::create(['nome' => 'Pedro', 'participacao_bps' => 5_000, 'ativo' => true]);
    $registrar = app(App\Actions\Socios\RegistrarLancamento::class);
    $base = ['descricao' => 'x', 'competencia' => '2026-09', 'ocorrido_em' => '2026-09-10', 'valor_cents' => 181_995, 'socio_id' => $socio->id];
    $registrar(NaturezaLancamento::Aporte, $base);
    $registrar(NaturezaLancamento::Despesa, ['descricao' => 'luz', 'competencia' => '2026-09', 'ocorrido_em' => '2026-09-10', 'valor_cents' => 1_000]);

    test()->artisan('avalia:apagar-aportes --simular')->assertSuccessful();
    expect(LancamentoFinanceiro::count())->toBe(2);

    test()->artisan('avalia:apagar-aportes')->assertSuccessful();
    expect(LancamentoFinanceiro::where('natureza', 'aporte')->count())->toBe(0)
        ->and(LancamentoFinanceiro::count())->toBe(1)
        ->and(saldo('caixa'))->toBe(-1_000)
        ->and(App\Models\Auditoria::where('acao', 'socios.lancamento.excluido')->count())->toBe(1);
});
