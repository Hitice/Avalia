<?php

use App\Support\Dinheiro;

it('formata centavos como moeda brasileira', function () {
    expect(Dinheiro::brl(90_000))->toBe("R$\u{00A0}900,00")
        ->and(Dinheiro::brl(7_990))->toBe("R$\u{00A0}79,90")
        ->and(Dinheiro::brl(0))->toBe("R$\u{00A0}0,00")
        ->and(Dinheiro::brl(500_000))->toBe("R$\u{00A0}5.000,00");
});

it('le o que o usuario digitou em qualquer formato usual', function () {
    expect(Dinheiro::paraCentavos('1.234,56'))->toBe(123_456)
        ->and(Dinheiro::paraCentavos('1234,56'))->toBe(123_456)
        ->and(Dinheiro::paraCentavos('1234.56'))->toBe(123_456)
        ->and(Dinheiro::paraCentavos('1234'))->toBe(123_400)
        ->and(Dinheiro::paraCentavos('R$ 79,90'))->toBe(7_990);
});

it('trata ponto de milhar sem virgula como milhar, nao como decimal', function () {
    // "1.234" digitado num campo de preco e mil duzentos e trinta e quatro
    // reais, nao um e vinte e tres.
    expect(Dinheiro::paraCentavos('1.234'))->toBe(123_400);
});

it('devolve nulo quando nao ha valor', function () {
    expect(Dinheiro::paraCentavos(''))->toBeNull()
        ->and(Dinheiro::paraCentavos(null))->toBeNull()
        ->and(Dinheiro::paraCentavos('R$'))->toBeNull();
});

it('nao perde centavo no arredondamento', function () {
    // O caso classico: (int) (19.99 * 100) da 1998 em ponto flutuante.
    expect(Dinheiro::paraCentavos('19,99'))->toBe(1_999)
        ->and(Dinheiro::paraCentavos(19.99))->toBe(1_999)
        ->and(Dinheiro::paraCentavos(0.29))->toBe(29);
});

it('aceita inteiro como reais inteiros', function () {
    expect(Dinheiro::paraCentavos(900))->toBe(90_000);
});
