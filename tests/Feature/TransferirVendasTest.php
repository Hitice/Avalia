<?php

use App\Models\Etiqueta;
use App\Models\Negocio;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passa placas vendidas, em maos e negocios de uma conta para outra, e so simula quando pedido', function () {
    $mestre = Staff::factory()->admin()->create(['email' => 'comercial@avaliaone.com.br']);
    $pedro = Staff::factory()->admin()->create(['email' => 'pedromuska@gmail.com', 'nome' => 'Pedro']);
    Etiqueta::factory()->ativa()->count(3)->create(['vendedor_id' => $mestre->id]);
    Etiqueta::factory()->count(2)->create(['vendida_em' => null, 'consignada_para_id' => $mestre->id]);
    Negocio::factory()->create(['vendedor_id' => $mestre->id]);
    Etiqueta::factory()->create(['vendida_em' => null, 'staff_id' => $mestre->id]);

    test()->artisan('avalia:transferir-vendas comercial@avaliaone.com.br pedromuska@gmail.com --simular')->assertSuccessful();
    expect(Etiqueta::where('vendedor_id', $pedro->id)->count())->toBe(0);

    test()->artisan('avalia:transferir-vendas comercial@avaliaone.com.br pedromuska@gmail.com')->assertSuccessful();

    expect(Etiqueta::where('vendedor_id', $pedro->id)->count())->toBe(3)
        ->and(Etiqueta::where('consignada_para_id', $pedro->id)->count())->toBe(2)
        ->and(Negocio::where('vendedor_id', $pedro->id)->count())->toBe(1)
        ->and(Etiqueta::where('staff_id', $pedro->id)->count())->toBeGreaterThanOrEqual(1)
        ->and(Etiqueta::where('staff_id', $mestre->id)->count())->toBe(0)
        ->and(Etiqueta::where('vendedor_id', $mestre->id)->count())->toBe(0)
        ->and(App\Models\Auditoria::where('acao', 'vendas.transferidas')->count())->toBe(1);

    test()->artisan('avalia:transferir-vendas ninguem@x.com pedromuska@gmail.com')->assertFailed();
});
