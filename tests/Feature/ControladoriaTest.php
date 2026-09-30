<?php

use App\Models\Etiqueta;
use App\Models\Socio;
use App\Models\Staff;
use App\Support\Dinheiro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Um admin com a permissao da casa, que e a mesma de Socios. */
function daCasa(): Tests\TestCase
{
    return test()
        ->actingAs(Staff::factory()->admin()->create(['super' => false, 'pode_socios' => true]), 'staff')
        ->withSession(['versao_staff' => 1]);
}

/*
|--------------------------------------------------------------------------
| Quem entra
|--------------------------------------------------------------------------
*/

it('nao abre para admin sem a permissao da casa', function () {
    $admin = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => false]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('controladoria'))
        ->assertForbidden();
});

it('nao abre para vendedor, mesmo com a permissao ligada por engano', function () {
    // Resultado da casa inclui custo e margem, que a PDD proibe ao vendedor.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'pode_socios' => true]);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('controladoria'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| O numero sai do razao
|--------------------------------------------------------------------------
*/

it('mostra o resultado do produto que lancou, somado do razao', function () {
    $socios = config('etiquetas.socios');
    Staff::factory()->admin()->create(['email' => $socios[0]]);
    Staff::factory()->admin()->create(['email' => $socios[1]]);

    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->ativa()->count(3)->create(['vendedor_id' => $warley->id]);

    test()->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    $valor = (int) config('etiquetas.precos.placa_cents');
    $custo = (int) config('etiquetas.custo_cents');
    $comissao = 3 * (int) round(($valor - $custo) * (int) config('etiquetas.comissao_pct') / 100);

    $html = daCasa()->get(route('controladoria'))->assertOk()->getContent();

    expect($html)->toContain(Dinheiro::brl(3 * $valor))
        ->and($html)->toContain(Dinheiro::brl(3 * $custo))
        ->and($html)->toContain(Dinheiro::brl($comissao))
        ->and($html)->toContain(Dinheiro::brl(3 * ($valor - $custo) - $comissao));
});

it('diz qual produto ainda nao lanca, em vez de mostrar zero calado', function () {
    // Zero sem aviso e lido como "nao vendeu". A diferenca entre nao vender e
    // nao estar ligado ao razao e o que esta tela existe para mostrar.
    $html = daCasa()->get(route('controladoria'))->assertOk()->getContent();

    expect($html)->toContain('não lança no razão ainda');
});

it('avisa quando as participacoes nao somam cem por cento', function () {
    Socio::create(['nome' => 'Pedro', 'participacao_bps' => 5_000, 'ativo' => true]);
    Socio::create(['nome' => 'Ruan', 'participacao_bps' => 3_000, 'ativo' => true]);

    $html = daCasa()->get(route('controladoria'))->assertOk()->getContent();

    expect($html)->toContain('somam 80,00%');
});

it('nao mostra tabela de socio quando nenhum esta cadastrado', function () {
    $html = daCasa()->get(route('controladoria'))->assertOk()->getContent();

    expect($html)->toContain('Nenhum sócio cadastrado');
});
