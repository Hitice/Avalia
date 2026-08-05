<?php

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Preco;
use App\Models\Servico;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Preco por faixa
|--------------------------------------------------------------------------
*/

it('cobra do plano o preco da faixa que ele contratou', function () {
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 500_000 => 370])
        ->create();

    $semMinimo = Plano::factory()->semMinimo()->create(['catalogo_id' => $catalogo->id]);
    $maiorFaixa = Plano::factory()->consumoMinimo(5_000)->create(['catalogo_id' => $catalogo->id]);

    expect($semMinimo->precoDe('scpc-bvs'))->toBe(631)
        ->and($maiorFaixa->precoDe('scpc-bvs'))->toBe(370);
});

it('devolve nulo quando o servico nao tem preco na faixa do plano', function () {
    $catalogo = Catalogo::factory()->comServico('renajud', [0 => 1_055])->create();
    $plano = Plano::factory()->consumoMinimo(900)->create(['catalogo_id' => $catalogo->id]);

    expect($plano->precoDe('renajud'))->toBeNull()
        ->and($plano->precoDe('servico-que-nao-existe'))->toBeNull();
});

it('lista as faixas que a versao oferece, em ordem', function () {
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [500_000 => 370, 0 => 631, 7_500 => 594])
        ->create();

    expect($catalogo->faixas())->toBe([0, 7_500, 500_000]);
});

it('recusa plano cujo consumo minimo nao e uma faixa do catalogo', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 7_500 => 594])->create();

    // R$ 300 nao e faixa: nenhuma consulta acharia coluna de preco.
    $torto = Plano::factory()->consumoMinimo(300)->create(['catalogo_id' => $catalogo->id]);
    $certo = Plano::factory()->consumoMinimo(75)->create(['catalogo_id' => $catalogo->id]);

    expect($torto->faixaValida())->toBeFalse()
        ->and($torto->podeVender())->toBeFalse()
        ->and($certo->faixaValida())->toBeTrue()
        ->and($certo->podeVender())->toBeTrue();
});

it('recusa dois precos para o mesmo servico na mesma faixa e catalogo', function () {
    $catalogo = Catalogo::factory()->create();
    $servico = Servico::factory()->create();

    Preco::factory()->for($catalogo, 'catalogo')->for($servico)->create(['preco_cents' => 631]);

    expect(fn () => Preco::factory()->for($catalogo, 'catalogo')->for($servico)->create(['preco_cents' => 594]))
        ->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| Servicos e franquia
|--------------------------------------------------------------------------
*/

it('esconde dos disponiveis o servico inativo e o que aguarda liberacao', function () {
    $liberado = Servico::factory()->create();
    $scr = Servico::factory()->aguardandoLiberacao()->create();
    $desligado = Servico::factory()->inativo()->create();

    expect($liberado->disponivel())->toBeTrue()
        ->and($scr->disponivel())->toBeFalse()
        ->and($desligado->disponivel())->toBeFalse()
        ->and(Servico::disponiveis()->pluck('id')->all())->toBe([$liberado->id]);
});

it('so oferece ao plano servico precificado na faixa dele e liberado', function () {
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [7_500 => 594])
        ->comServico('renajud', [0 => 1_055])
        ->comServico('scr-score', [7_500 => 1_883])
        ->create();

    Servico::where('codigo', 'scr-score')->update(['exige_liberacao' => true]);

    $plano = Plano::factory()->consumoMinimo(75)->create(['catalogo_id' => $catalogo->id]);

    // renajud existe, mas so tem preco sem minimo; scr-score esta travado.
    expect($plano->servicosDisponiveis()->pluck('codigo')->all())->toBe(['scpc-bvs']);
});

it('conta franquia por quantidade de consultas, nao por valor', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [7_500 => 594])->create();
    $plano = Plano::factory()->consumoMinimo(75)->create(['catalogo_id' => $catalogo->id]);

    $plano->franquias()->create([
        'servico_id' => Servico::where('codigo', 'scpc-bvs')->value('id'),
        'quantidade' => 50,
    ]);

    expect($plano->franquiaDe('scpc-bvs'))->toBe(50)
        ->and($plano->franquiaDe('renajud'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Tabela de referencia
|--------------------------------------------------------------------------
*/

it('importa a tabela do fornecedor como rascunho, nunca ativa', function () {
    $this->seed(CatalogoSeeder::class);

    $catalogo = Catalogo::firstWhere('rotulo', 'Tabela de referência 04/2026');

    // Ativa direto significaria vender com preco nao homologado.
    expect(Servico::count())->toBe(43)
        ->and($catalogo->precos()->count())->toBe(43 * 7);
});

it('mantem cada preco na faixa certa ao importar', function () {
    // Guarda contra o erro de transcricao original: os PDFs tem sete colunas
    // (sem minimo + seis faixas) e a primeira leitura tratou como seis,
    // jogando todo preco uma faixa para o lado.
    $this->seed(CatalogoSeeder::class);

    $catalogo = Catalogo::firstWhere('rotulo', 'Tabela de referência 04/2026');

    expect($catalogo->faixas())->toBe([0, 7_500, 20_000, 50_000, 90_000, 150_000, 500_000])
        ->and($catalogo->precoDe('cheques-sem-fundos', 0))->toBe(209)
        ->and($catalogo->precoDe('cheques-sem-fundos', 7_500))->toBe(188)
        ->and($catalogo->precoDe('cheques-sem-fundos', 500_000))->toBe(98)
        ->and($catalogo->precoDe('vip-car', 0))->toBe(5_530)
        ->and($catalogo->precoDe('vip-car', 500_000))->toBe(4_468);
});

it('marca os servicos de SCR como pendentes de liberacao', function () {
    $this->seed(CatalogoSeeder::class);

    $travados = Servico::where('exige_liberacao', true)->pluck('codigo')->sort()->values()->all();

    expect($travados)->toBe(['prime-completa-scr', 'relatorio-top-scr', 'scr-score']);
});

it('roda duas vezes sem duplicar servico nem preco', function () {
    $this->seed(CatalogoSeeder::class);
    $this->seed(CatalogoSeeder::class);

    expect(Servico::count())->toBe(43)
        ->and(Preco::count())->toBe(43 * 7)
        ->and(Catalogo::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Apresentacao
|--------------------------------------------------------------------------
*/

it('formata os valores do plano em reais', function () {
    $plano = Plano::factory()->consumoMinimo(900)->create(['mensalidade_cents' => 7_990]);

    expect($plano->mensalidade)->toBe("R$\u{00A0}79,90")
        ->and($plano->consumo_minimo)->toBe("R$\u{00A0}900,00")
        ->and($plano->fatura_minima)->toBe("R$\u{00A0}979,90");
});

it('escreve "sem minimo" em vez de R$ 0,00', function () {
    // Zero centavos e ausencia de piso, nao piso de nada. A regra mora em
    // Dinheiro::faixa e o plano so a repassa.
    $plano = Plano::factory()->semMinimo()->create();

    expect($plano->consumo_minimo)->toBe('Sem mínimo')
        ->and(App\Support\Dinheiro::faixa(0))->toBe('Sem mínimo')
        ->and(App\Support\Dinheiro::faixa(90_000))->toBe("R$\u{00A0}900,00");
});

it('so calcula margem quando o custo do fornecedor esta cadastrado', function () {
    $semCusto = Preco::factory()->create(['preco_cents' => 631]);
    $comCusto = Preco::factory()->create(['preco_cents' => 631, 'custo_cents' => 400]);

    expect($semCusto->margemCents())->toBeNull()
        ->and($comCusto->margemCents())->toBe(231);
});
