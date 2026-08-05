<?php

use App\Support\Margem;

// Cenario da Avalia: imposto 13,5%, comissao 10% do lucro, margem alvo 30%.
const IMPOSTO = 1_350;
const COMISSAO = 1_000;
const ALVO = 3_000;

it('desconta o fisco, depois o fornecedor, e comissiona o que sobrou', function () {
    // Venda R$ 5,28, imposto R$ 0,71, custo R$ 2,80: sobra R$ 1,77 de lucro, e
    // o vendedor leva 10% dele.
    expect(Margem::impostoCents(528, IMPOSTO))->toBe(71)
        ->and(Margem::baseComissaoCents(528, 280, IMPOSTO))->toBe(177)
        ->and(Margem::comissaoCents(528, 280, COMISSAO, IMPOSTO))->toBe(18)
        ->and(Margem::liquidaCents(528, 280, IMPOSTO, COMISSAO))->toBe(159)
        ->and(Margem::pct(528, 280, IMPOSTO, COMISSAO))->toBe(30.1);
});

it('deixa a Avalia com 90% do lucro, qualquer que seja o preco', function () {
    // E a consequencia aritmetica de comissionar sobre lucro, e o que faz o
    // interesse do vendedor coincidir com o da operacao.
    foreach ([400, 528, 1_000, 9_999] as $venda) {
        $lucro = Margem::baseComissaoCents($venda, 280, IMPOSTO);

        expect(Margem::liquidaCents($venda, 280, IMPOSTO, COMISSAO))
            ->toBe($lucro - (int) round($lucro / 10), "venda {$venda}");
    }
});

it('nao paga comissao sobre prejuizo, nem cobra do vendedor', function () {
    // Comissao negativa faria o vendedor pagar para ter vendido.
    expect(Margem::baseComissaoCents(300, 280, IMPOSTO))->toBeLessThan(0)
        ->and(Margem::comissaoCents(300, 280, COMISSAO, IMPOSTO))->toBe(0);
});

it('calcula o preco que entrega a margem pedida', function () {
    $alvo = Margem::precoAlvoCents(280, IMPOSTO, COMISSAO, ALVO);

    expect($alvo)->toBe(528)
        ->and(Margem::atinge($alvo, 280, IMPOSTO, COMISSAO, ALVO))->toBeTrue()
        // Um centavo abaixo ja nao serve, senao o alvo estaria alto demais.
        ->and(Margem::atinge($alvo - 1, 280, IMPOSTO, COMISSAO, ALVO))->toBeFalse();
});

it('acha o alvo para qualquer custo, sempre pelo menor preco que serve', function () {
    foreach ([49, 85, 150, 895, 1_997, 2_297, 5_500] as $custo) {
        $alvo = Margem::precoAlvoCents($custo, IMPOSTO, COMISSAO, ALVO);

        expect(Margem::atinge($alvo, $custo, IMPOSTO, COMISSAO, ALVO))->toBeTrue("custo {$custo}")
            ->and(Margem::atinge($alvo - 1, $custo, IMPOSTO, COMISSAO, ALVO))->toBeFalse("custo {$custo}");
    }
});

it('trata o piso como o alvo de margem zero', function () {
    $piso = Margem::pisoCents(280, IMPOSTO, COMISSAO);

    expect($piso)->toBe(Margem::precoAlvoCents(280, IMPOSTO, COMISSAO, 0))
        ->and(Margem::daPrejuizo($piso, 280, IMPOSTO, COMISSAO))->toBeFalse()
        ->and(Margem::daPrejuizo($piso - 1, 280, IMPOSTO, COMISSAO))->toBeTrue();
});

it('nao muda o piso por causa da comissao', function () {
    // Comissionando sobre lucro, no piso o lucro e zero e a comissao tambem: o
    // menor preco que nao da prejuizo e o mesmo com ou sem vendedor. Antes, com
    // comissao sobre faturamento, ela empurrava o piso para cima.
    expect(Margem::pisoCents(280, IMPOSTO, COMISSAO))
        ->toBe(Margem::pisoCents(280, IMPOSTO, 0));
});

it('mostra margem negativa em vez de esconder', function () {
    expect(Margem::liquidaCents(300, 280, IMPOSTO, COMISSAO))->toBeLessThan(0)
        ->and(Margem::daPrejuizo(300, 280, IMPOSTO, COMISSAO))->toBeTrue();
});

it('nao inventa nada enquanto o custo nao esta cadastrado', function () {
    expect(Margem::liquidaCents(545, null, IMPOSTO, COMISSAO))->toBeNull()
        ->and(Margem::pct(545, null, IMPOSTO, COMISSAO))->toBeNull()
        ->and(Margem::pisoCents(null, IMPOSTO, COMISSAO))->toBeNull()
        ->and(Margem::precoAlvoCents(null, IMPOSTO, COMISSAO, ALVO))->toBeNull()
        ->and(Margem::baseComissaoCents(545, null, IMPOSTO))->toBeNull()
        ->and(Margem::comissaoCents(545, null, COMISSAO, IMPOSTO))->toBeNull()
        ->and(Margem::daPrejuizo(545, null, IMPOSTO, COMISSAO))->toBeFalse()
        ->and(Margem::atinge(545, null, IMPOSTO, COMISSAO, ALVO))->toBeFalse();
});

it('desiste em vez de dividir por zero quando o alvo e impossivel', function () {
    // Com 13,5% de imposto e 10% de comissao sobram 77,8 pontos da venda: alvo
    // de 90% de margem nao existe a preco nenhum, e a resposta honesta e null.
    expect(Margem::precoAlvoCents(280, IMPOSTO, COMISSAO, 9_000))->toBeNull()
        ->and(Margem::precoAlvoCents(280, 5_000, 3_000, 2_000))->toBeGreaterThan(0);
});
