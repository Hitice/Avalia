<?php

use App\Support\Margem;

// Cenario da Avalia: imposto 8,6%, comissao 10%, margem alvo 30%.
const IMPOSTO = 860;
const COMISSAO = 1_000;
const ALVO = 3_000;

it('desconta fornecedor, fisco e vendedor da venda', function () {
    // Venda R$ 5,45, custo R$ 2,80, imposto R$ 0,47, comissao R$ 0,55.
    expect(Margem::impostoCents(545, IMPOSTO))->toBe(47)
        ->and(Margem::comissaoCents(545, COMISSAO))->toBe(55)
        ->and(Margem::liquidaCents(545, 280, IMPOSTO, COMISSAO))->toBe(163)
        ->and(Margem::pct(545, 280, IMPOSTO, COMISSAO))->toBe(29.9);
});

it('conta a comissao como custo, e nao como sobra', function () {
    // Sem a comissao a margem parece 10 pontos maior do que e.
    $comComissao = Margem::pct(545, 280, IMPOSTO, COMISSAO);
    $semComissao = Margem::pct(545, 280, IMPOSTO, 0);

    expect(round($semComissao - $comComissao, 1))->toBe(10.1);
});

it('calcula o preco que entrega a margem pedida', function () {
    $alvo = Margem::precoAlvoCents(280, IMPOSTO, COMISSAO, ALVO);

    expect($alvo)->toBe(546)
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

it('o piso sobe quando a comissao entra na conta', function () {
    // Ignorar a comissao faz o piso parecer menor do que e, e o preco que
    // parecia empatar na verdade perde dinheiro.
    $semComissao = Margem::pisoCents(280, IMPOSTO, 0);
    $comComissao = Margem::pisoCents(280, IMPOSTO, COMISSAO);

    expect($comComissao)->toBeGreaterThan($semComissao)
        ->and(Margem::daPrejuizo($semComissao, 280, IMPOSTO, COMISSAO))->toBeTrue();
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
        ->and(Margem::daPrejuizo(545, null, IMPOSTO, COMISSAO))->toBeFalse()
        ->and(Margem::atinge(545, null, IMPOSTO, COMISSAO, ALVO))->toBeFalse();
});

it('nao estoura quando as aliquotas somam 100%', function () {
    // Sem teto, o divisor zeraria e o alvo viraria divisao por zero.
    expect(Margem::precoAlvoCents(280, 5_000, 3_000, 2_000))->toBeGreaterThan(0)
        ->and(Margem::precoAlvoCents(280, 9_000, 9_000, 9_000))->toBeGreaterThan(0);
});
