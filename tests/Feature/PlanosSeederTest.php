<?php

use App\Models\Catalogo;
use App\Models\Plano;
use Database\Seeders\PlanosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria um plano por faixa do catalogo', function () {
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 90_000 => 493])
        ->create();

    $this->seed(PlanosSeeder::class);

    expect(Plano::count())->toBe(3)
        ->and(Plano::pluck('consumo_minimo_cents')->sort()->values()->all())->toBe([0, 7_500, 90_000])
        ->and(Plano::pluck('mensalidade_cents')->unique()->all())->toBe([7_990]);
});

it('deixa todo plano criado com faixa valida e vendavel', function () {
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 90_000 => 493])
        ->create();

    $this->seed(PlanosSeeder::class);

    Plano::each(function (Plano $plano) {
        expect($plano->faixaValida())->toBeTrue()
            ->and($plano->podeVender())->toBeTrue()
            ->and($plano->precoDe('scpc-bvs'))->not->toBeNull();
    });
});

it('roda duas vezes sem duplicar e sem desfazer ajuste manual', function () {
    Catalogo::factory()->comServico('scpc-bvs', [90_000 => 493])->create();

    $this->seed(PlanosSeeder::class);

    $plano = Plano::first();
    $plano->update(['mensalidade_cents' => 9_990]);

    $this->seed(PlanosSeeder::class);

    expect(Plano::count())->toBe(1)
        ->and($plano->fresh()->mensalidade_cents)->toBe(9_990);
});

it('nao cria plano quando a versao nao tem preco', function () {
    Catalogo::factory()->create();

    $this->seed(PlanosSeeder::class);

    expect(Plano::count())->toBe(0);
});

it('nao quebra quando nao existe versao nenhuma', function () {
    $this->seed(PlanosSeeder::class);

    expect(Plano::count())->toBe(0);
});
