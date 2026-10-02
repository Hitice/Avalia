<?php

use App\Models\Etiqueta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A busca do QR dinamico, tecla a tecla
|--------------------------------------------------------------------------
*/

it('acha o codigo pela metade, como foi digitado', function () {
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);
    Etiqueta::factory()->create(['codigo' => 'ABCDEF']);

    $html = admin()->get(route('etiquetas.index', ['busca' => 'k7m']))->assertOk()->getContent();

    expect($html)->toContain('K7M2PX')->and($html)->not->toContain('ABCDEF');
});

it('devolve so a tabela para a busca ao vivo, sem carregar parcial nos links de pagina', function () {
    Etiqueta::factory()->count(26)->create();

    $parcial = admin()->get(route('etiquetas.index', ['parcial' => 1, 'situacao' => 'nova']))->assertOk()->getContent();

    expect($parcial)->toContain('id="tabela-etiquetas"')
        ->and($parcial)->not->toContain('<h1')
        ->and($parcial)->toContain('situacao=nova')
        ->and($parcial)->not->toContain('parcial=');

    $inteira = admin()->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($inteira)->toContain('<h1')->and($inteira)->toContain('id="tabela-etiquetas"');
});
