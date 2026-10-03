<?php

use App\Models\Cliente;
use App\Models\Contato;
use App\Models\Lead;
use App\Models\Negocio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Uma pessoa, um contato, cinco frentes
|--------------------------------------------------------------------------
*/

it('da o mesmo contato ao cliente do One e ao negocio do Sales com o mesmo documento', function () {
    $cliente = Cliente::factory()->create(['razao_social' => 'Padaria do Zé LTDA', 'cnpj' => '12.345.678/0001-95', 'telefone' => '(34) 99999-0000']);
    $negocio = Negocio::factory()->create(['nome' => 'Padaria do Zé', 'documento' => '12345678000195', 'whatsapp' => '34999990000']);

    expect($cliente->fresh()->contato_id)->not->toBeNull()
        ->and($negocio->fresh()->contato_id)->toBe($cliente->fresh()->contato_id)
        ->and(Contato::count())->toBe(1);

    $contato = Contato::sole();
    expect($contato->papeis())->toBe(['cliente', 'negocio'])
        ->and($contato->documento)->toBe('12345678000195')
        ->and($contato->whatsapp)->toBe('34999990000')
        ->and($contato->interacoes()->count())->toBe(2);
});

it('deduplica por WhatsApp quando nao ha documento, e nunca pelo nome sozinho', function () {
    Negocio::factory()->create(['nome' => 'Bar do João', 'whatsapp' => '(34) 98888-1111', 'documento' => null]);
    Lead::factory()->create(['nome' => 'Bar do Joao', 'telefone' => '34988881111', 'cnpj' => null, 'email' => null]);
    Negocio::factory()->create(['nome' => 'Bar do João', 'whatsapp' => null, 'documento' => null, 'email' => null, 'telefone' => null]);

    expect(Contato::count())->toBe(2)
        ->and(collect(Contato::where('whatsapp', '34988881111')->sole()->papeis())->sort()->values()->all())->toBe(['lead', 'negocio']);
});

it('lastreia o que ja existe sem duplicar, e a tela lista e busca', function () {
    $cliente = Cliente::factory()->create(['razao_social' => 'Mercearia Central', 'cnpj' => '11.222.333/0001-81']);
    Cliente::withoutEvents(fn () => Cliente::factory()->create(['razao_social' => 'Sem Contato ME', 'cnpj' => '22.333.444/0001-70']));
    expect(Cliente::whereNull('contato_id')->count())->toBe(1);

    test()->artisan('avalia:lastrear-contatos')->assertSuccessful();
    test()->artisan('avalia:lastrear-contatos')->assertSuccessful();

    expect(Cliente::whereNull('contato_id')->count())->toBe(0)->and(Contato::count())->toBe(2);

    $html = admin()->get(route('gestao.contatos', ['busca' => '11.222']))->assertOk()->getContent();
    expect($html)->toContain('Mercearia Central')->not->toContain('Sem Contato ME');

    admin()->get(route('gestao.contatos.ver', $cliente->fresh()->contato_id))->assertOk()->assertSee('Cadastrado como cliente');
});
