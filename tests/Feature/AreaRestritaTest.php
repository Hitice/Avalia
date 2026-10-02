<?php

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Porta fechada sem jogar a pessoa fora da tela
|--------------------------------------------------------------------------
*/

it('nao mostra Negocios ao vendedor, que nao pode abrir', function () {
    $maria = Staff::factory()->create(['papel' => 'vendedor']);

    $html = comoVendedor($maria)->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($html)->not->toContain('href="/negocios"');
});

it('devolve o vendedor que veio por link para a tela de origem, com o aviso', function () {
    $maria = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($maria)->from(route('sales.inicio'))->get(route('negocios'))
        ->assertRedirect(route('sales.inicio'))
        ->assertSessionHas('erro', 'Área restrita à administração.');
});

it('mostra o cadeado no painel a quem digitou o endereco', function () {
    $maria = Staff::factory()->create(['papel' => 'vendedor']);

    $html = comoVendedor($maria)->get(route('negocios'))->assertForbidden()->getContent();

    expect($html)->toContain('aria-label="Cadeado"')
        ->and($html)->toContain('Área restrita à administração.')
        ->and($html)->toContain('Voltar ao início');
});
