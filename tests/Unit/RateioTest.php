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

    // O split do provedor incide sobre o liquido: de 100.000 saem 349 de
    // taxa dele, e o produtor recebe 95% dos 99.651 que sobraram.
    expect($r['bruto'])->toBe(100000)
        ->and($r['taxa_provedor'])->toBe(-349)
        ->and($r['repasse'])->toBe(-94668)
        ->and($r['taxa_plataforma'])->toBe(-4983)
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

it('reparte o liquido, como o provedor reparte', function () {
    // Este teste guarda a correcao de uma divergencia real: o razao cobrava a
    // taxa sobre o bruto enquanto o split do provedor a aplicava sobre o
    // liquido, e as duas contas diferiam em 5% da taxa do provedor por
    // parcela. Num carne de doze, isso vira discussao no extrato.
    $comTaxaBaixa = Rateio::de(100000, 100, 500);
    $comTaxaAlta = Rateio::de(100000, 900, 500);

    // Taxa do provedor maior deixa menos liquido, e a plataforma ganha menos:
    // e o comportamento do provedor, e o razao tem que contar a mesma
    // historia que o extrato.
    expect($comTaxaBaixa['repasse'])->toBe(-94905)
        ->and($comTaxaAlta['repasse'])->toBe(-94145)
        ->and($comTaxaBaixa['taxa_plataforma'])->toBe(-4995)
        ->and($comTaxaAlta['taxa_plataforma'])->toBe(-4955)
        ->and(array_sum($comTaxaBaixa))->toBe(0)
        ->and(array_sum($comTaxaAlta))->toBe(0);
});

it('chega ao mesmo numero que o percentual mandado ao provedor', function () {
    // A ponte entre as duas pontas: o que o razao grava como repasse tem que
    // ser o que o percentual do split produz sobre o liquido. Se alguem mexer
    // numa das duas formulas, este teste quebra.
    $pago = 57300;
    $taxaProvedor = 412;
    $bps = 500;

    $r = Rateio::de($pago, $taxaProvedor, $bps);
    $pelaRegraDoSplit = (int) floor(($pago - $taxaProvedor) * (Rateio::percentualDoProdutor($bps) / 100));

    expect(-$r['repasse'])->toBe($pelaRegraDoSplit);
});

it('manda para a carteira do produtor o que sobra da taxa', function () {
    // O split vai em percentual: 5% de taxa deixam 95% para o produtor.
    expect(Rateio::percentualDoProdutor(500))->toBe(95.0)
        ->and(Rateio::percentualDoProdutor(1250))->toBe(87.5);
});
