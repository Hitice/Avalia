<?php

use App\Models\Preco;
use App\Models\Servico;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CustosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('poe o mesmo custo em todas as faixas do servico', function () {
    // O fornecedor cobra por consulta, nao por faixa contratada pelo cliente.
    $this->seed(CatalogoSeeder::class);
    $this->seed(CustosSeeder::class);

    $scpc = Servico::firstWhere('codigo', 'scpc-bvs');
    $custos = $scpc->precos()->pluck('custo_cents')->unique();

    expect($custos->all())->toBe([280])
        ->and($scpc->precos()->count())->toBe(7);
});

it('deixa sem custo o veicular, que nao veio na tabela', function () {
    $this->seed(CatalogoSeeder::class);
    $this->seed(CustosSeeder::class);

    foreach (['vip-car', 'renajud', 'gravame'] as $codigo) {
        $servico = Servico::firstWhere('codigo', $codigo);

        expect($servico->precos()->whereNotNull('custo_cents')->count())
            ->toBe(0, "esperava {$codigo} sem custo");
    }
});

it('nao desfaz custo ajustado a mao', function () {
    $this->seed(CatalogoSeeder::class);

    $scpc = Servico::firstWhere('codigo', 'scpc-bvs');
    $ajustado = $scpc->precos()->first();
    $ajustado->update(['custo_cents' => 999]);

    $this->seed(CustosSeeder::class);

    expect($ajustado->fresh()->custo_cents)->toBe(999)
        // As demais faixas do mesmo servico foram preenchidas normalmente.
        ->and($scpc->precos()->where('custo_cents', 280)->count())->toBe(6);
});

it('roda duas vezes sem mudar nada na segunda', function () {
    $this->seed(CatalogoSeeder::class);
    $this->seed(CustosSeeder::class);

    $soma = Preco::sum('custo_cents');
    $comCusto = Preco::whereNotNull('custo_cents')->count();

    $this->seed(CustosSeeder::class);

    expect(Preco::sum('custo_cents'))->toBe($soma)
        ->and(Preco::whereNotNull('custo_cents')->count())->toBe($comCusto);
});

it('nao quebra sem catalogo', function () {
    $this->seed(CustosSeeder::class);

    expect(Preco::count())->toBe(0);
});

it('cobre os 26 servicos de credito', function () {
    $this->seed(CatalogoSeeder::class);
    $this->seed(CustosSeeder::class);

    $comCusto = Servico::whereHas('precos', fn ($q) => $q->whereNotNull('custo_cents'))->count();

    expect($comCusto)->toBe(26)
        ->and(Servico::count())->toBe(43)
        // 26 servicos × 7 faixas.
        ->and(Preco::whereNotNull('custo_cents')->count())->toBe(182);
});

it('nao mexe no preco de venda', function () {
    $this->seed(CatalogoSeeder::class);
    $antes = Preco::sum('preco_cents');

    $this->seed(CustosSeeder::class);

    expect(Preco::sum('preco_cents'))->toBe($antes);
});
