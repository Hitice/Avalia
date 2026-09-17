<?php

use App\Support\Rateio;

/**
 * A reparticao de cada pagamento entre provedor, plataforma e produtor.
 *
 * O que estes testes guardam e a invariante: as quatro partes somam zero. Foi
 * ela que transformou erro de arredondamento em teste vermelho em vez de
 * divergencia de centavo aparecendo no extrato do produtor tres meses depois.
 */
it('reparte o pagamento e fecha em zero', function () {
    $r = Rateio::de(pagoCents: 100000, taxaProvedorCents: 349, taxaBps: 500);

    expect($r['bruto'])->toBe(100000)
        ->and($r['taxa_provedor'])->toBe(-349)
        ->and($r['taxa_plataforma'])->toBe(-5000)
        ->and($r['repasse'])->toBe(-94651)
        ->and(array_sum($r))->toBe(0);
});

it('fecha em zero mesmo quando a divisao sobra centavo', function () {
    // 3,33% de 10.001 nao e inteiro: a sobra tem que ir para alguem, e o teste
    // exige que ela nao suma.
    $r = Rateio::de(pagoCents: 10001, taxaProvedorCents: 199, taxaBps: 333);

    expect(array_sum($r))->toBe(0)
        ->and($r['repasse'])->toBeLessThan(0);
});

it('nao deixa o produtor receber negativo', function () {
    // Parcela pequena com taxa de provedor alta: a plataforma absorve, e o
    // produtor recebe zero em vez de dever dinheiro.
    $r = Rateio::de(pagoCents: 300, taxaProvedorCents: 349, taxaBps: 500);

    expect($r['repasse'])->toBe(0)
        ->and(array_sum($r))->toBe(0);
});

it('cobra a taxa sobre o pago, e nao sobre o liquido', function () {
    // 5% de 100.000 sao 5.000, independentemente do que o provedor cobrou.
    $comTaxaBaixa = Rateio::de(100000, 100, 500);
    $comTaxaAlta = Rateio::de(100000, 900, 500);

    expect($comTaxaBaixa['taxa_plataforma'])->toBe(-5000)
        ->and($comTaxaAlta['taxa_plataforma'])->toBe(-5000);
});

it('manda para a carteira do produtor o que sobra da taxa', function () {
    // O split vai em percentual: 5% de taxa deixam 95% para o produtor.
    expect(Rateio::percentualDoProdutor(500))->toBe(95.0)
        ->and(Rateio::percentualDoProdutor(1250))->toBe(87.5);
});
