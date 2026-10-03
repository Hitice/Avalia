<?php

use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('e a lateral do CRM, com contatos e leads, so de administracao', function () {
    Cliente::factory()->create(['razao_social' => 'Mercearia Central']);

    $html = admin()->get(route('crm.inicio'))->assertOk()->getContent();

    expect($html)->toContain('Avalia CRM')
        ->and($html)->toContain('href="/crm/contatos"')->toContain('href="/leads"')
        ->and($html)->toContain('href="/erp"')->toContain('href="/painel"')
        ->and($html)->not->toContain('href="/equipe"');

    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    comoVendedor($maria)->get(route('crm.inicio'))->assertForbidden();
});
