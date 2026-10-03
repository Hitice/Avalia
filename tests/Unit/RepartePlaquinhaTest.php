<?php

use App\Support\RepartePlaquinha;

/*
|--------------------------------------------------------------------------
| O reparte de uma placa
|--------------------------------------------------------------------------
*/

it('tira o custo antes de comissionar', function () {
    // A placa de 89,90 custa 5,00. A comissao de 25% incide sobre os 89,90 da
    // sobram, e nao sobre o preco cheio: 21,23 e nao 22,48.
    $parte = RepartePlaquinha::de(8_990, 500, true, 25);

    expect($parte['liquido'])->toBe(8_490)
        ->and($parte['comissao'])->toBe(2_248)
        ->and($parte['lucro'])->toBe(6_242);
});

it('manda o liquido inteiro para a divisao quando nao ha comissao', function () {
    // Duas situacoes chegam aqui: venda de socio, que ja recebe pela divisao, e
    // venda sem vendedor, em que o cliente apontou o proprio codigo e nao houve
    // venda de ninguem. Nos dois casos nao ha comissao a pagar.
    $parte = RepartePlaquinha::de(8_990, 500, false, 25);

    expect($parte['comissao'])->toBe(0)
        ->and($parte['lucro'])->toBe(8_490);
});

it('fecha sempre no bruto', function () {
    // A invariante que pega erro de arredondamento sem reconferir extrato:
    // custo + comissao + sobra devolvem o que o cliente pagou.
    foreach ([8_990, 7_990, 1_00, 3_333] as $valor) {
        $parte = RepartePlaquinha::de($valor, 500, true, 25);

        expect($parte['custo'] + $parte['comissao'] + $parte['lucro'])->toBe($parte['bruto']);
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
    $lucroDoMes = 0;

    for ($i = 0; $i < 100; $i++) {
        $lucroDoMes += RepartePlaquinha::de(8_990, 500, true, 25)['lucro'];
    }

    [$primeiro, $segundo] = RepartePlaquinha::dividir($lucroDoMes, 2);

    expect($primeiro - $segundo)->toBeLessThanOrEqual(1)
        ->and($primeiro + $segundo)->toBe($lucroDoMes);
});

it('paga a comissao sobre a venda mesmo abaixo do custo, e a casa absorve o prejuizo', function () {
    // Comissao sobre o VALOR DE VENDA (02/10/2026): quem vendeu recebe os 25%
    // de qualquer jeito, e o prejuizo e da casa. O lucro negativo fica visivel
    // de proposito: esconde-lo seria mentir no razao. So acontece se alguem
    // corrigir um valor abaixo do custo.
    $parte = RepartePlaquinha::de(300, 500, true, 25);

    expect($parte['custo'])->toBe(300)
        ->and($parte['liquido'])->toBe(0)
        ->and($parte['comissao'])->toBe(75)
        ->and($parte['lucro'])->toBe(-75);
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

/*
|--------------------------------------------------------------------------
| A retencao: metade da parte volta ao caixa
|--------------------------------------------------------------------------
*/

it('devolve metade da parte ao caixa e deixa metade de pro-labore', function () {
    // Em R$ 100 de lucro cada socio tem R$ 50: R$ 25 ficam, R$ 25 saem.
    expect(RepartePlaquinha::retencao(5_000, 50))->toBe(['retido' => 2_500, 'prolabore' => 2_500]);
});

it('deixa o centavo impar com o socio, e nao com o caixa', function () {
    // Um centavo a menos no repasse e o que gera reclamacao.
    expect(RepartePlaquinha::retencao(5_001, 50))->toBe(['retido' => 2_500, 'prolabore' => 2_501]);
});

it('fecha sempre: retido mais pro-labore e a parte inteira', function () {
    foreach ([0, 1, 99, 5_000, 5_001, 123_457] as $parte) {
        $r = RepartePlaquinha::retencao($parte, 50);
        expect($r['retido'] + $r['prolabore'])->toBe($parte);
    }
});

it('nao inventa parte negativa nem retencao fora de 0 a 100', function () {
    expect(RepartePlaquinha::retencao(-10, 50))->toBe(['retido' => 0, 'prolabore' => 0])
        ->and(RepartePlaquinha::retencao(1_000, 150)['retido'])->toBe(1_000)
        ->and(RepartePlaquinha::retencao(1_000, -5)['retido'])->toBe(0);
});

it('divide o prejuizo sem perder centavo', function () {
    expect(App\Support\RepartePlaquinha::dividir(-7, 2))->toBe([-4, -3])
        ->and(array_sum(App\Support\RepartePlaquinha::dividir(-1001, 3)))->toBe(-1001);
});
