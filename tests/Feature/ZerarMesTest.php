<?php

use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('apaga o razao e as vendas do mes, e deixa a placa no ar', function () {
    $emails = config('etiquetas.socios');
    Staff::factory()->admin()->create(['email' => $emails[0]]);
    Staff::factory()->admin()->create(['email' => $emails[1]]);
    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    $setembro = Etiqueta::factory()->ativa()->create(['vendedor_id' => $maria->id, 'vendida_em' => '2026-09-15 10:00:00']);
    $outubro = Etiqueta::factory()->ativa()->create(['vendedor_id' => $maria->id, 'vendida_em' => '2026-10-01 10:00:00']);
    test()->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();
    expect(LancamentoFinanceiro::count())->toBe(2);

    test()->artisan('avalia:zerar-mes 2026-09 --simular')->assertSuccessful();
    expect(LancamentoFinanceiro::count())->toBe(2);

    test()->artisan('avalia:zerar-mes 2026-09')->assertSuccessful();

    $setembro->refresh();
    expect($setembro->vendida_em)->toBeNull()->and($setembro->destino)->not->toBeNull()->and($setembro->codigo)->not->toBeNull()
        ->and($outubro->fresh()->vendida_em)->not->toBeNull()
        ->and(LancamentoFinanceiro::where('competencia', '2026-09')->count())->toBe(0)
        ->and(LancamentoFinanceiro::count())->toBe(1)
        ->and(App\Models\Auditoria::where('acao', 'mes.zerado')->count())->toBe(1);

    test()->artisan('avalia:zerar-mes setembro')->assertFailed();
});

it('zera pela tela do ERP, com o mes confirmado', function () {
    $socio = Staff::factory()->admin()->create(['pode_socios' => true]);
    $como = test()->actingAs($socio, 'staff')->withSession(['versao_staff' => $socio->sessao_versao]);

    $como->from(route('erp.inicio'))->post(route('erp.razao.zerar-mes'), ['competencia' => '2026-09', 'confirmo' => '2026-08'])->assertSessionHasErrors('confirmo');
    $como->from(route('erp.inicio'))->post(route('erp.razao.zerar-mes'), ['competencia' => '2026-09', 'confirmo' => '2026-09'])->assertRedirect(route('erp.inicio'))->assertSessionHas('ok');
});
