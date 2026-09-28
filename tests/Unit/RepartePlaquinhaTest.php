<?php

use App\Support\RepartePlaquinha;

/*
|--------------------------------------------------------------------------
| O reparte de uma placa
|--------------------------------------------------------------------------
*/

it('tira o custo antes de comissionar', function () {
    // A placa de 89,90 custa 5,00. A comissao de 25% incide sobre os 84,90 que
    // sobram, e nao sobre o preco cheio: 21,23 e nao 22,48.
    $parte = RepartePlaquinha::de(8_990, 500, true, 25);

    expect($parte['liquido'])->toBe(8_490)
        ->and($parte['comissao'])->toBe(2_123)
        ->and($parte['sobra'])->toBe(6_367);
});

it('manda o liquido inteiro para a divisao quando nao ha comissao', function () {
    // Duas situacoes chegam aqui: venda de socio, que ja recebe pela divisao, e
    // venda sem vendedor, em que o cliente apontou o proprio codigo e nao houve
    // venda de ninguem. Nos dois casos nao ha comissao a pagar.
    $parte = RepartePlaquinha::de(8_990, 500, false, 25);

    expect($parte['comissao'])->toBe(0)
        ->and($parte['sobra'])->toBe(8_490);
});

it('fecha sempre no bruto', function () {
    // A invariante que pega erro de arredondamento sem reconferir extrato:
    // custo + comissao + sobra devolvem o que o cliente pagou.
    foreach ([8_990, 7_990, 1_00, 3_333] as $valor) {
        $parte = RepartePlaquinha::de($valor, 500, true, 25);

        expect($parte['custo'] + $parte['comissao'] + $parte['sobra'])->toBe($parte['bruto']);
    }
});

it('da o centavo impar sempre ao primeiro da lista', function () {
    // Estavel entre dois F5: o numero do socio nao pode mudar sozinho.
    expect(RepartePlaquinha::dividir(6_367, 2))->toBe([3_184, 3_183])
        ->and(RepartePlaquinha::dividir(6_367, 2))->toBe([3_184, 3_183])
        ->and(RepartePlaquinha::dividir(10, 3))->toBe([4, 3, 3]);
});

it('nao acumula vies quando a divisao e feita uma vez no fim', function () {
    // Cem placas de vendedor comum. Dividindo venda a venda, o centavo impar
    // cairia sempre no mesmo socio e viraria cinquenta centavos de diferenca;
    // somando antes, a diferenca e de um centavo, no maximo.
    $sobraDoMes = 0;

    for ($i = 0; $i < 100; $i++) {
        $sobraDoMes += RepartePlaquinha::de(8_990, 500, true, 25)['sobra'];
    }

    [$primeiro, $segundo] = RepartePlaquinha::dividir($sobraDoMes, 2);

    expect($primeiro - $segundo)->toBeLessThanOrEqual(1)
        ->and($primeiro + $segundo)->toBe($sobraDoMes);
});

it('nao deixa o custo virar comissao negativa', function () {
    // Placa vendida abaixo do custo e prejuizo da casa. Quem vendeu nao paga
    // para ter vendido.
    $parte = RepartePlaquinha::de(300, 500, true, 25);

    expect($parte['custo'])->toBe(300)
        ->and($parte['liquido'])->toBe(0)
        ->and($parte['comissao'])->toBe(0)
        ->and($parte['sobra'])->toBe(0);
});

it('ignora percentual absurdo em vez de estourar o repasse', function () {
    // Config com 900% nao pode virar comissao maior que a venda.
    expect(RepartePlaquinha::comissaoCents(8_490, 900))
        ->toBe(RepartePlaquinha::comissaoCents(8_490, 50));
});

it('sobrevive a config sem socio nenhum', function () {
    // Lista vazia mostra uma coluna a menos; dividir por zero derrubaria a tela.
    expect(RepartePlaquinha::dividir(6_367, 0))->toBe([]);
});
