<?php

use App\Models\Preco;
use App\Support\Comissao;
use App\Support\Dinheiro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A simulacao do vendedor e a do administrador respondem perguntas diferentes.
 *
 * A do administrador decide se o preco da lucro, e por isso mostra custo,
 * imposto e margem. A do vendedor decide se cabe desconto, e por isso mostra o
 * que a empresa paga e o que ele recebe. Sao telas separadas, e nao a mesma com
 * condicionais: cada campo novo na tela unica nasce a um `@if` de vazar.
 */
it('mostra ao vendedor a fatura do cliente e a comissao dele', function () {
    [$vendedor] = carteira();

    $resposta = comoVendedor($vendedor)
        ->get(route('carteira.simulacao', ['faixa' => '900,00', 'mensalidade' => '79,90', 'consumo' => '900,00']))
        ->assertOk();

    $proposta = $resposta->viewData('proposta');

    expect($proposta['fatura_cents'])->toBe(7_990 + 90_000)
        ->and($proposta['comissao_cents'])->toBeGreaterThan(0);
});

it('nao entrega custo, imposto nem lucro para a tela do vendedor', function () {
    [$vendedor] = carteira();

    $resposta = comoVendedor($vendedor)->get(route('carteira.simulacao'))->assertOk();

    // O array de Simulacao traz os tres juntos. O controller escolhe o que
    // manda, entao nem chega a existir variavel de view para imprimir.
    expect(array_keys($resposta->viewData('proposta')))->not->toContain('custo_cents')
        ->not->toContain('imposto_cents')
        ->not->toContain('lucro_cents')
        ->not->toContain('margem_pct');
});

it('usa a aliquota do proprio vendedor na simulacao', function () {
    [$vendedor] = carteira();

    $padrao = comoVendedor($vendedor)->get(route('carteira.simulacao'))
        ->assertOk()->viewData('proposta')['comissao_cents'];

    $vendedor->update(['comissao_pct' => Comissao::PCT_PADRAO * 2]);

    $dobro = comoVendedor($vendedor)->get(route('carteira.simulacao'))
        ->assertOk()->viewData('proposta')['comissao_cents'];

    expect($dobro)->toBe($padrao * 2);
});

it('mostra ao vendedor o preco de venda dos servicos e nenhum custo', function () {
    [$vendedor] = carteira();

    $resposta = comoVendedor($vendedor)->get(route('carteira.servicos'))->assertOk();

    $custo = Preco::first()->custo_cents;

    expect($resposta->viewData('precos')->first()->preco_cents)->toBe(324)
        ->and($custo)->toBe(150);

    // O custo esta cadastrado e nao sai na tela. O `select` do controller nem o
    // carrega, entao nao ha atributo a imprimir por descuido.
    $resposta->assertSee(Dinheiro::brl(324))->assertDontSee(Dinheiro::brl(150));
});

it('mostra todas as faixas lado a lado com o numero do servico', function () {
    // A comparacao que o vendedor faz ao telefone, sem trocar de tela: a
    // coluna e o plano, a linha e o servico, e o numero se dita ("consulta 7").
    [$vendedor, , $servico] = carteira();

    $catalogo = App\Models\Catalogo::first();
    App\Models\Plano::factory()->consumoMinimo(200)->create([
        'catalogo_id' => $catalogo->id,
        'nome' => 'Plano Entrada',
        'mensalidade_cents' => 4_990,
    ]);
    $catalogo->precos()->create([
        'servico_id' => $servico->id,
        'consumo_minimo_cents' => 20_000,
        'preco_cents' => 410,
        'custo_cents' => 150,
    ]);

    $html = comoVendedor($vendedor)->get(route('carteira.servicos'))->assertOk()->getContent();

    // As duas faixas na mesma tela, e o numero de atendimento na linha.
    expect($html)->toContain(Dinheiro::brl(324))
        ->toContain(Dinheiro::brl(410))
        ->toContain('Plano Entrada')
        ->and(App\Models\Servico::firstWhere('codigo', 'scpc-bvs')->numero)->not->toBeNull();
});

it('da numero sequencial e nunca reusado a servico novo', function () {
    carteira();

    $maior = (int) App\Models\Servico::max('numero');
    $novo = App\Models\Servico::factory()->create();

    expect($novo->numero)->toBe($maior + 1);
});
