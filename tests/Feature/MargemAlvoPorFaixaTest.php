<?php

use App\Actions\Catalogo\AjustarPrecosAoAlvo;
use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Margem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A margem alvo de cada faixa, e o reajuste que a atinge.
 *
 * O alvo e uma escada: a faixa sem minimo entrega a margem cheia e cada faixa
 * seguinte cede um degrau, porque quem se compromete com mais consumo paga
 * menos por consulta. Dizer isso pelo lado da margem, e nao pelo do desconto,
 * e o que faz o servico barato parar de afundar: desconto percentual igual
 * para todo mundo ignora que o custo do fornecedor e fixo.
 *
 * O reajuste SO SOBE, e nunca roda sozinho.
 */
function catalogoComAlvo(array $precos = [0 => 300, 90_000 => 100]): Catalogo
{
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', $precos)->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    return $catalogo->fresh();
}

it('desce um degrau de margem a cada faixa, e nunca abaixo de zero', function () {
    $catalogo = Catalogo::factory()->create(['margem_alvo_bps' => 3_000, 'degrau_margem_bps' => 500]);

    $alvos = $catalogo->margemAlvoPorFaixa([0, 7_500, 20_000, 50_000, 90_000, 150_000, 500_000]);

    expect(array_values($alvos))->toBe([3_000, 2_500, 2_000, 1_500, 1_000, 500, 0]);

    // Degrau grande nao vira alvo negativo: a casa nao planeja prejuizo.
    $agressivo = Catalogo::factory()->create(['margem_alvo_bps' => 1_000, 'degrau_margem_bps' => 800]);

    expect(array_values($agressivo->margemAlvoPorFaixa([0, 7_500, 20_000])))->toBe([1_000, 200, 0]);
});

it('encontra o preco que rende menos que a margem alvo da propria faixa', function () {
    $catalogo = catalogoComAlvo();

    $abaixo = AjustarPrecosAoAlvo::abaixoDoAlvo($catalogo);
    $porFaixa = $abaixo->keyBy(fn ($linha) => $linha['preco']->consumo_minimo_cents);

    // Custo de R$ 1,50: R$ 1,00 na faixa funda nao cobre nem o custo.
    expect($porFaixa)->toHaveKey(90_000)
        ->and($porFaixa[90_000]['alvo'])->toBeGreaterThan(150);

    // E o alvo da faixa funda e menor que o da primeira, porque ela cedeu
    // degraus: a escada precisa aparecer no numero, nao so no comentario.
    $alvos = $catalogo->margemAlvoPorFaixa([0, 90_000]);
    expect($alvos[90_000])->toBeLessThan($alvos[0]);
});

it('nao inventa reajuste onde o custo nao esta cadastrado', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 100])->create();
    $catalogo->precos()->update(['custo_cents' => null]);

    expect(AjustarPrecosAoAlvo::abaixoDoAlvo($catalogo->fresh()))->toHaveCount(0);
});

it('sobe ao alvo e deixa em paz o preco que ja rende mais', function () {
    // R$ 9,00 com custo de R$ 1,50 rende muito acima de qualquer alvo.
    $catalogo = catalogoComAlvo([0 => 900, 90_000 => 100]);

    $ajustados = app(AjustarPrecosAoAlvo::class)($catalogo);

    expect($ajustados)->toBe(1)
        // Preco gordo fica onde esta: o alvo e o minimo aceito, nao a media
        // perseguida, e derrubar quem rende bem seria abrir mao de dinheiro.
        ->and(Preco::where('consumo_minimo_cents', 0)->value('preco_cents'))->toBe(900)
        ->and(AjustarPrecosAoAlvo::abaixoDoAlvo($catalogo->fresh()))->toHaveCount(0);
});

it('deixa a faixa exatamente na margem alvo depois do ajuste', function () {
    $catalogo = catalogoComAlvo();

    app(AjustarPrecosAoAlvo::class)($catalogo);
    $catalogo = $catalogo->fresh();

    $alvoBps = $catalogo->margemAlvoPorFaixa([0, 90_000])[90_000];
    $preco = Preco::where('consumo_minimo_cents', 90_000)->sole();

    expect(Margem::atinge(
        $preco->preco_cents, $preco->custo_cents,
        $catalogo->imposto_bps, $catalogo->comissaoBps(), $alvoBps,
    ))->toBeTrue();

    // E e o MENOR preco que atinge: reajustar mais que o necessario e cobrar
    // do cliente uma folga que a politica nao pediu.
    expect(Margem::atinge(
        $preco->preco_cents - 1, $preco->custo_cents,
        $catalogo->imposto_bps, $catalogo->comissaoBps(), $alvoBps,
    ))->toBeFalse();
});

it('registra cada preco na trilha, com o alvo que motivou', function () {
    $catalogo = catalogoComAlvo();

    app(AjustarPrecosAoAlvo::class)($catalogo);

    $trilha = App\Models\Auditoria::where('acao', 'preco.alvo')->sole();

    expect($trilha->dados['de_cents'])->toBe(100)
        ->and($trilha->dados['margem_alvo_bps'])->toBeInt()
        ->and(App\Support\Rotulos::acao('preco.alvo'))->toBe('Preço subiu para a margem alvo');
});

it('avisa na tabela e oferece o ajuste, sem aplicar sozinho', function () {
    catalogoComAlvo();

    admin()->get(route('catalogo.tabela'))
        ->assertOk()
        ->assertSee('menos que a margem alvo da própria faixa', false)
        ->assertSee(route('catalogo.precos.alvo'), false);

    expect(Preco::where('consumo_minimo_cents', 90_000)->value('preco_cents'))->toBe(100);
});

it('mostra a escada de alvos na pagina de parametros', function () {
    catalogoComAlvo();

    admin()->get(route('catalogo.parametros'))
        ->assertOk()
        ->assertSee('Margem alvo')
        ->assertSee('Degrau por faixa')
        // Salvar parametro nao pode ser confundido com reajustar preco.
        ->assertSee('não altera preço nenhum', false);
});

it('nao deixa vendedor reajustar a tabela', function () {
    catalogoComAlvo();

    comoVendedor(App\Models\Staff::factory()->create(['papel' => 'vendedor']))
        ->post(route('catalogo.precos.alvo'))
        ->assertForbidden();
});
