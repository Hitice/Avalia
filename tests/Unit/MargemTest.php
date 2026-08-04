<?php

use App\Support\Margem;

it('desconta custo do fornecedor e imposto da venda', function () {
    // Venda R$ 16,90, custo R$ 10,90, imposto 27% = R$ 4,56.
    expect(Margem::impostoCents(1_690, 2_700))->toBe(456)
        ->and(Margem::liquidaCents(1_690, 1_090, 2_700))->toBe(144)
        ->and(Margem::pct(1_690, 1_090, 2_700))->toBe(8.5);
});

it('mostra margem negativa em vez de esconder', function () {
    // Vender a R$ 12,00 o que custa R$ 10,90 nao paga o imposto.
    expect(Margem::liquidaCents(1_200, 1_090, 2_700))->toBe(-214)
        ->and(Margem::daPrejuizo(1_200, 1_090, 2_700))->toBeTrue()
        ->and(Margem::daPrejuizo(1_690, 1_090, 2_700))->toBeFalse();
});

it('nao inventa margem enquanto o custo nao esta cadastrado', function () {
    expect(Margem::liquidaCents(1_690, null, 2_700))->toBeNull()
        ->and(Margem::pct(1_690, null, 2_700))->toBeNull()
        ->and(Margem::pisoCents(null, 2_700))->toBeNull()
        ->and(Margem::daPrejuizo(1_690, null, 2_700))->toBeFalse();
});

it('calcula o piso onde a margem zera', function () {
    // custo 10,90 ÷ (1 − 0,27) = 14,93.
    $piso = Margem::pisoCents(1_090, 2_700);

    expect($piso)->toBe(1_493)
        ->and(Margem::liquidaCents($piso, 1_090, 2_700))->toBe(0)
        ->and(Margem::daPrejuizo($piso, 1_090, 2_700))->toBeFalse();
});

it('arredonda o piso para cima, nunca para dentro do prejuizo', function () {
    // Um centavo abaixo do piso ja tem que acusar prejuizo.
    foreach ([100, 631, 5_530, 55_000] as $custo) {
        $piso = Margem::pisoCents($custo, 2_700);

        expect(Margem::daPrejuizo($piso, $custo, 2_700))->toBeFalse()
            ->and(Margem::daPrejuizo($piso - 1, $custo, 2_700))->toBeTrue();
    }
});

it('trata imposto zero sem quebrar', function () {
    expect(Margem::impostoCents(1_690, 0))->toBe(0)
        ->and(Margem::liquidaCents(1_690, 1_090, 0))->toBe(600)
        ->and(Margem::pisoCents(1_090, 0))->toBe(1_090);
});

it('nao aceita aliquota de 100% ou mais', function () {
    // Sem o teto, o piso dividiria por zero ou viraria negativo.
    expect(Margem::pisoCents(1_090, 10_000))->toBeGreaterThan(0)
        ->and(Margem::pisoCents(1_090, 50_000))->toBeGreaterThan(0);
});
