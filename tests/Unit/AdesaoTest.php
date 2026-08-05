<?php

use App\Models\Adesao;

/*
|--------------------------------------------------------------------------
| Parcelamento da adesao
|--------------------------------------------------------------------------
|
| Dividir dinheiro em parcelas iguais quase nunca fecha: R$ 1.000,00 em tres
| nao da tres parcelas identicas. O que nao pode acontecer e a soma das
| parcelas ser diferente do total combinado com o cliente.
|
*/

it('soma exatamente o total, qualquer que seja o numero de parcelas', function () {
    foreach ([100_000, 120_000, 99_999, 1, 33] as $total) {
        foreach ([1, 2, 3, 6, 7, 12, 24] as $vezes) {
            $parcelas = Adesao::parcelasDe($total, $vezes);

            expect(array_sum($parcelas))->toBe($total, "{$total} em {$vezes}x")
                ->and($parcelas)->toHaveCount($vezes);
        }
    }
});

it('poe a sobra na primeira parcela, nao na ultima', function () {
    // R$ 1.000,00 em tres: 333,34 + 333,33 + 333,33. A sobra vai na primeira
    // porque e a que o cliente confere na assinatura, e porque adiar a
    // diferenca faria a ultima cobranca destoar meses depois.
    expect(Adesao::parcelasDe(100_000, 3))->toBe([33_334, 33_333, 33_333]);
});

it('nao inventa parcela de adesao isentada', function () {
    // Isentar e ausencia de cobranca, nao desconto.
    expect(Adesao::parcelasDe(0, 12))->toBe([]);
});

it('trata numero de parcelas invalido como uma so', function () {
    expect(Adesao::parcelasDe(50_000, 0))->toBe([50_000])
        ->and(Adesao::parcelasDe(50_000, -3))->toBe([50_000]);
});
