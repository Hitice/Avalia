<?php

use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('e a home da equipe, com o que esta na mao e o que vendeu no mes', function () {
    $emails = config('etiquetas.socios');
    Staff::factory()->admin()->create(['email' => $emails[0]]);
    Staff::factory()->admin()->create(['email' => $emails[1]]);
    $maria = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Maria']);
    Etiqueta::factory()->count(3)->create(['vendida_em' => null, 'consignada_para_id' => $maria->id]);
    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $maria->id]);

    $html = test()->actingAs($maria, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('sales.inicio'))->assertOk()->getContent();

    $comissao = 2 * (int) round((int) config('etiquetas.precos.placa_cents') * (int) config('etiquetas.comissao_pct') / 100);

    expect($html)->toContain('Avalia Sales')
        ->and($html)->toContain('Placas disponíveis')
        ->and($html)->toContain('Comissão atual')
        ->and($html)->toContain(App\Support\Dinheiro::brl($comissao))
        // Vendedor nao ve a equipe.
        ->and($html)->not->toContain('Equipe no mês');
});

it('mostra ao socio a parte dele no lucro do mes, ja com a retencao', function () {
    $emails = config('etiquetas.socios');
    $pedro = Staff::factory()->admin()->create(['email' => $emails[0]]);
    Staff::factory()->admin()->create(['email' => $emails[1]]);
    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->ativa()->count(3)->create(['vendedor_id' => $maria->id]);
    Etiqueta::factory()->ativa()->create(['vendedor_id' => $pedro->id]);

    $html = test()->actingAs($pedro, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('sales.inicio'))->assertOk()->getContent();

    $valor = (int) config('etiquetas.precos.placa_cents');
    $custo = (int) config('etiquetas.custo_cents');
    $comissao = (int) round($valor * (int) config('etiquetas.comissao_pct') / 100);
    // Tres vendas da Maria comissionam; a do Pedro nao. O lucro de todas divide entre dois.
    $lucro = 3 * ($valor - $custo - $comissao) + ($valor - $custo);
    $parte = App\Support\RepartePlaquinha::dividir($lucro, 2)[0];
    $prolabore = App\Support\RepartePlaquinha::retencao($parte, (int) config('etiquetas.retencao_pct'))['prolabore'];

    expect($html)->toContain('Pró-labore do mês')
        ->and($html)->toContain(App\Support\Dinheiro::brl($prolabore))
        ->and($html)->toContain('Parte '.App\Support\Dinheiro::brl($parte))
        ->and($html)->not->toContain('Minha comissão no mês');
});

it('mostra a equipe ao admin, sem cartao pessoal para quem nao vende', function () {
    $html = admin()->get(route('sales.inicio'))->assertOk()->getContent();

    expect($html)->toContain('Equipe no mês')->and($html)->toContain('Estoque atual')
        ->and($html)->not->toContain('Comissão atual')->not->toContain('Vendas do mês');
});

it('nao abre para cliente nem sem sessao', function () {
    $this->get(route('sales.inicio'))->assertRedirect();

    $empresa = App\Models\Cliente::factory()->create();
    test()->actingAs($empresa, 'empresa')->withSession(['versao_empresa' => 1])
        ->get(route('sales.inicio'))->assertRedirect();
});

it('poe Inicio no menu do Sales e leva a marca para la', function () {
    $html = admin()->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($html)->toContain('href="/sales"');
});
