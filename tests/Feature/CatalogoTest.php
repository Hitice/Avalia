<?php

use App\Exceptions\CatalogoCongelado;
use App\Models\Plano;
use App\Models\Preco;
use App\Models\Servico;
use App\Models\VersaoCatalogo;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Comissao do vendedor
|--------------------------------------------------------------------------
*/

it('paga 20% ate a faixa de R$ 900 e 15% acima dela', function () {
    expect(Plano::factory()->consumoMinimo(75)->create()->pctComissao())->toBe(20)
        ->and(Plano::factory()->consumoMinimo(900)->create()->pctComissao())->toBe(20)
        ->and(Plano::factory()->consumoMinimo(1_500)->create()->pctComissao())->toBe(15);
});

it('nao deixa a mensalidade empurrar o plano para a faixa de baixo', function () {
    // O plano de R$ 900 fatura R$ 979,90 com a mensalidade. Se a faixa lesse a
    // fatura em vez do consumo minimo, o vendedor cairia de 20% para 15% sem
    // ninguem ter mudado o contrato.
    $plano = Plano::factory()->consumoMinimo(900)->create(['mensalidade_cents' => 7_990]);

    expect($plano->faturaMinimaCents())->toBe(97_990)
        ->and($plano->pctComissao())->toBe(20);
});

/*
|--------------------------------------------------------------------------
| Preco por faixa
|--------------------------------------------------------------------------
*/

it('cobra do plano o preco da faixa que ele contratou', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 500_000 => 370])
        ->create();

    $semMinimo = Plano::factory()->semMinimo()->create(['versao_id' => $versao->id]);
    $maiorFaixa = Plano::factory()->consumoMinimo(5_000)->create(['versao_id' => $versao->id]);

    expect($semMinimo->precoDe('scpc-bvs'))->toBe(631)
        ->and($maiorFaixa->precoDe('scpc-bvs'))->toBe(370);
});

it('devolve nulo quando o servico nao tem preco na faixa do plano', function () {
    $versao = VersaoCatalogo::factory()->comServico('renajud', [0 => 1_055])->create();
    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => $versao->id]);

    expect($plano->precoDe('renajud'))->toBeNull()
        ->and($plano->precoDe('servico-que-nao-existe'))->toBeNull();
});

it('lista as faixas que a versao oferece, em ordem', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [500_000 => 370, 0 => 631, 7_500 => 594])
        ->create();

    expect($versao->faixas())->toBe([0, 7_500, 500_000]);
});

it('recusa plano cujo consumo minimo nao e uma faixa da versao', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631, 7_500 => 594])->create();

    // R$ 300 nao e faixa: nenhuma consulta acharia coluna de preco.
    $torto = Plano::factory()->consumoMinimo(300)->create(['versao_id' => $versao->id]);
    $certo = Plano::factory()->consumoMinimo(75)->create(['versao_id' => $versao->id]);

    expect($torto->faixaValida())->toBeFalse()
        ->and($torto->podeVender())->toBeFalse()
        ->and($certo->faixaValida())->toBeTrue()
        ->and($certo->podeVender())->toBeTrue();
});

it('recusa dois precos para o mesmo servico na mesma faixa e versao', function () {
    $versao = VersaoCatalogo::factory()->create();
    $servico = Servico::factory()->create();

    Preco::factory()->for($versao, 'versao')->for($servico)->create(['preco_cents' => 631]);

    expect(fn () => Preco::factory()->for($versao, 'versao')->for($servico)->create(['preco_cents' => 594]))
        ->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| Congelamento
|--------------------------------------------------------------------------
*/

it('nao aceita preco novo em versao ja ativa', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs')->ativa()->create();
    $servico = Servico::factory()->create();

    expect(fn () => $versao->precos()->create([
        'servico_id' => $servico->id,
        'consumo_minimo_cents' => 0,
        'preco_cents' => 100,
    ]))->toThrow(CatalogoCongelado::class);
});

it('nao aceita alterar nem apagar preco de versao ativa', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->ativa()->create();
    $preco = $versao->precos()->first();

    expect(fn () => $preco->update(['preco_cents' => 1]))->toThrow(CatalogoCongelado::class)
        ->and(fn () => $preco->delete())->toThrow(CatalogoCongelado::class);

    expect($versao->precos()->first()->preco_cents)->toBe(631);
});

it('deixa editar enquanto a versao e rascunho', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $versao->precos()->first()->update(['preco_cents' => 600]);

    expect($versao->precos()->first()->preco_cents)->toBe(600);
});

it('duplica a versao para reajuste sem tocar na original', function () {
    $original = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594])
        ->ativa()
        ->create();

    $nova = $original->duplicar('Catálogo 05/2026');

    expect($nova->situacao)->toBe('rascunho')
        ->and($nova->precos()->count())->toBe(2)
        ->and($nova->precoDe('scpc-bvs', 0))->toBe(631);

    $nova->precos()->where('consumo_minimo_cents', 0)->first()->update(['preco_cents' => 700]);

    expect($nova->precoDe('scpc-bvs', 0))->toBe(700)
        ->and($original->precoDe('scpc-bvs', 0))->toBe(631);
});

it('mantem uma unica versao ativa e encerra a anterior', function () {
    $antiga = VersaoCatalogo::factory()->ativa()->create();
    $nova = VersaoCatalogo::factory()->create();

    $nova->ativar();

    expect($antiga->fresh()->situacao)->toBe('encerrada')
        ->and($antiga->fresh()->vigencia_fim)->not->toBeNull()
        ->and(VersaoCatalogo::ativa()->count())->toBe(1)
        ->and(VersaoCatalogo::vigente()->is($nova))->toBeTrue();
});

it('carimba a data em que a versao deixou de ser editavel', function () {
    $versao = VersaoCatalogo::factory()->create();
    expect($versao->congelada_em)->toBeNull();

    $versao->ativar();

    expect($versao->congelada_em)->not->toBeNull()
        ->and($versao->podeEditar())->toBeFalse();
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
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [7_500 => 594])
        ->comServico('renajud', [0 => 1_055])
        ->comServico('scr-score', [7_500 => 1_883])
        ->create();

    Servico::where('codigo', 'scr-score')->update(['exige_liberacao' => true]);

    $plano = Plano::factory()->consumoMinimo(75)->create(['versao_id' => $versao->id]);

    // renajud existe, mas so tem preco sem minimo; scr-score esta travado.
    expect($plano->servicosDisponiveis()->pluck('codigo')->all())->toBe(['scpc-bvs']);
});

it('conta franquia por quantidade de consultas, nao por valor', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [7_500 => 594])->create();
    $plano = Plano::factory()->consumoMinimo(75)->create(['versao_id' => $versao->id]);

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

    $versao = VersaoCatalogo::firstWhere('rotulo', 'Tabela de referência 04/2026');

    // Ativa direto significaria vender com preco nao homologado.
    expect($versao->situacao)->toBe('rascunho')
        ->and(VersaoCatalogo::vigente())->toBeNull()
        ->and(Servico::count())->toBe(43)
        ->and($versao->precos()->count())->toBe(43 * 7);
});

it('mantem cada preco na faixa certa ao importar', function () {
    // Guarda contra o erro de transcricao original: os PDFs tem sete colunas
    // (sem minimo + seis faixas) e a primeira leitura tratou como seis,
    // jogando todo preco uma faixa para o lado.
    $this->seed(CatalogoSeeder::class);

    $versao = VersaoCatalogo::firstWhere('rotulo', 'Tabela de referência 04/2026');

    expect($versao->faixas())->toBe([0, 7_500, 20_000, 50_000, 90_000, 150_000, 500_000])
        ->and($versao->precoDe('cheques-sem-fundos', 0))->toBe(209)
        ->and($versao->precoDe('cheques-sem-fundos', 7_500))->toBe(188)
        ->and($versao->precoDe('cheques-sem-fundos', 500_000))->toBe(98)
        ->and($versao->precoDe('vip-car', 0))->toBe(5_530)
        ->and($versao->precoDe('vip-car', 500_000))->toBe(4_468);
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
        ->and(VersaoCatalogo::count())->toBe(1);
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
    $plano = Plano::factory()->semMinimo()->create();
    $preco = Preco::factory()->create(['consumo_minimo_cents' => 0]);

    expect($plano->consumo_minimo)->toBe('Sem mínimo')
        ->and($preco->faixa)->toBe('Sem mínimo');
});

it('so calcula margem quando o custo do fornecedor esta cadastrado', function () {
    $semCusto = Preco::factory()->create(['preco_cents' => 631]);
    $comCusto = Preco::factory()->create(['preco_cents' => 631, 'custo_cents' => 400]);

    expect($semCusto->margemCents())->toBeNull()
        ->and($semCusto->custo)->toBeNull()
        ->and($comCusto->margemCents())->toBe(231);
});
