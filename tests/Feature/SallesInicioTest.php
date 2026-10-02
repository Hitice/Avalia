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
        ->get(route('salles.inicio'))->assertOk()->getContent();

    $comissao = 2 * (int) round((int) config('etiquetas.precos.placa_cents') * (int) config('etiquetas.comissao_pct') / 100);

    expect($html)->toContain('Avalia Salles')
        ->and($html)->toContain('Na minha mão')
        ->and($html)->toContain(App\Support\Dinheiro::brl($comissao))
        // Vendedor nao ve a equipe.
        ->and($html)->not->toContain('Equipe no mês');
});

it('mostra a equipe ao admin', function () {
    $html = admin()->get(route('salles.inicio'))->assertOk()->getContent();

    expect($html)->toContain('Equipe no mês')->and($html)->toContain('Livres no estoque da casa');
});

it('nao abre para cliente nem sem sessao', function () {
    $this->get(route('salles.inicio'))->assertRedirect();

    $empresa = App\Models\Cliente::factory()->create();
    test()->actingAs($empresa, 'empresa')->withSession(['versao_empresa' => 1])
        ->get(route('salles.inicio'))->assertRedirect();
});

it('poe Inicio no menu do Salles e leva a marca para la', function () {
    $html = admin()->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($html)->toContain('href="/sales"');
});
