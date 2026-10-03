<?php

use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminSemPlacas(): Tests\TestCase
{
    $conta = Staff::factory()->admin()->create(['pode_placas' => false]);

    return test()->actingAs($conta, 'staff')->withSession(['versao_staff' => $conta->sessao_versao])->withHeaders(['referer' => '']);
}

it('so quem cuida das placas gera e entrega; o menu e a tela escondem o resto', function () {
    adminSemPlacas()->get(route('etiquetas.criar'))->assertForbidden();
    adminSemPlacas()->post(route('sales.estoque.entregar'), ['vendedor_id' => 1, 'quantas' => 1])->assertForbidden();

    $html = adminSemPlacas()->get(route('sales.estoque'))->assertOk()->getContent();
    expect($html)->not->toContain('href="/etiquetas/gerar"')->not->toContain('Entregar placas');

    admin()->get(route('etiquetas.criar'))->assertOk();
});

it('a administracao diz de quem e a venda, e quem vende aponta no proprio nome', function () {
    $maria = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Maria']);
    $placa = Etiqueta::factory()->create(['vendida_em' => null]);

    admin()->from(route('etiquetas.ficha', $placa))->put(route('etiquetas.apontar', $placa), ['destino' => 'https://exemplo.com.br'])
        ->assertSessionHasErrors('vendedor_id');

    admin()->put(route('etiquetas.apontar', $placa), ['destino' => 'https://exemplo.com.br', 'vendedor_id' => $maria->id]);
    expect($placa->fresh()->vendedor_id)->toBe($maria->id);

    $outra = Etiqueta::factory()->create(['vendida_em' => null, 'consignada_para_id' => $maria->id]);
    comoVendedor($maria)->put(route('etiquetas.apontar', $outra), ['destino' => 'https://exemplo.com.br']);
    expect($outra->fresh()->vendedor_id)->toBe($maria->id);
});
