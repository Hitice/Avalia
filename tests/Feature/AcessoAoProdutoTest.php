<?php

use App\Models\Staff;
use App\Support\Porta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Quem da equipe entra em que produto
|--------------------------------------------------------------------------
*/

it('fecha o Sales para quem so trabalha no One, e o One para quem so vende na rua', function () {
    $juliana = Staff::factory()->create(['papel' => 'vendedor', 'acessa_sales' => false]);
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'acessa_one' => false]);

    comoVendedor($juliana)->get(route('sales.inicio'))->assertForbidden();
    comoVendedor($juliana)->get(route('etiquetas.index'))->assertForbidden();
    comoVendedor($juliana)->get(route('painel'))->assertOk();

    comoVendedor($warley)->get(route('painel'))->assertForbidden();
    comoVendedor($warley)->get(route('sales.inicio'))->assertOk();
    comoVendedor($warley)->get(route('etiquetas.index'))->assertOk();
    // Conta e termos nao sao de produto nenhum.
    comoVendedor($warley)->get(route('perfil'))->assertOk();
});

it('leva quem so vende na rua direto para o Sales, e tira a porta do menu de quem nao entra', function () {
    $juliana = Staff::factory()->create(['papel' => 'vendedor', 'acessa_sales' => false]);
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'acessa_one' => false]);

    comoVendedor($warley);
    expect(Porta::painelDe('staff'))->toBe(route('sales.inicio'))
        ->and(Porta::entradaDe('credito'))->toBeNull()
        ->and(Porta::entradaDe('vendas'))->toBe(route('sales.inicio'));

    comoVendedor($juliana)->get(route('painel'))->assertOk()->assertDontSee('href="/sales"', false);
    expect(Porta::entradaDe('vendas'))->toBeNull();
});

it('liga e desliga o acesso no clique, com rastro, e nao tem chave para administracao', function () {
    $maria = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Maria']);

    // Antes dos from(): a referencia fica na instancia e transformaria o 403 em volta.
    comoVendedor($maria)->patch(route('equipe.acesso', [$maria, 'one']))->assertForbidden();

    $html = admin()->get(route('equipe.index'))->assertOk()->getContent();
    expect($html)->toContain(route('equipe.acesso', [$maria, 'sales']))
        ->and($html)->toContain(route('equipe.acesso', [$maria, 'one']));

    admin()->from(route('equipe.index'))->patch(route('equipe.acesso', [$maria, 'sales']))
        ->assertRedirect(route('equipe.index'))->assertSessionHas('ok');
    expect($maria->fresh()->acessa_sales)->toBeFalse()
        ->and(App\Models\Auditoria::where('acao', 'equipe.alterada')->count())->toBe(1);

    admin()->patch(route('equipe.acesso', [$maria, 'sales']));
    expect($maria->fresh()->acessa_sales)->toBeTrue();

    $chefe = Staff::factory()->admin()->create();
    admin()->from(route('equipe.index'))->patch(route('equipe.acesso', [$chefe, 'one']))->assertSessionHas('erro');
    expect($chefe->fresh()->acessa('one'))->toBeTrue();

});
