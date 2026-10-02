<?php

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('e a lateral do back office, so de administracao, com as outras areas no pe', function () {
    $html = admin()->get(route('gestao.inicio'))->assertOk()->getContent();

    expect($html)->toContain('Avalia Gestão')
        ->and($html)->toContain('href="/equipe"')->toContain('href="/auditoria"')
        // Socios exige a permissao propria; some de quem nao a tem.
        ->and($html)->not->toContain('href="/socios"')
        // As outras areas, e nao as telas delas.
        ->and($html)->toContain('href="/painel"')->toContain('href="/sales"')
        ->and($html)->not->toContain('href="/catalogo"');

    $socio = Staff::factory()->admin()->create(['pode_socios' => true]);
    test()->actingAs($socio, 'staff')->withSession(['versao_staff' => $socio->sessao_versao])
        ->get(route('gestao.inicio'))->assertOk()->assertSee('href="/socios"', false);

    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    comoVendedor($maria)->get(route('gestao.inicio'))->assertForbidden();
});

it('tira da lateral do One o que e da casa, e deixa a porta da Gestao', function () {
    $html = admin()->get(route('painel'))->assertOk()->getContent();

    expect($html)->toContain('href="/gestao"')->toContain('href="/sales"')->toContain('href="/catalogo"')
        ->and($html)->not->toContain('href="/equipe"')->not->toContain('href="/auditoria"');

    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    $doVendedor = comoVendedor($maria)->get(route('painel'))->assertOk()->getContent();
    expect($doVendedor)->not->toContain('href="/gestao"');
});
