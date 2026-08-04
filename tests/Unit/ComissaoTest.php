<?php

use App\Support\Comissao;

it('paga 10% sobre o consumo do mes', function () {
    expect(Comissao::pct())->toBe(10)
        ->and(Comissao::cents(30_000))->toBe(3_000)
        ->and(Comissao::cents(97_990))->toBe(9_799);
});

it('sobe para 20% no mes com excedente, sobre o consumo inteiro', function () {
    // O adicional vale para o plano todo na competencia, nao so para a parcela
    // que passou da franquia.
    expect(Comissao::pct(true))->toBe(20)
        ->and(Comissao::cents(30_000, true))->toBe(6_000);
});

it('le o consumo realizado, nao a fatura', function () {
    // Plano de R$ 900 de minimo, cliente consumiu R$ 300: a fatura sai
    // R$ 979,90 mas a comissao e sobre os R$ 300.
    $consumoRealizado = 30_000;
    $fatura = 7_990 + 90_000;

    expect(Comissao::cents($consumoRealizado))->toBe(3_000)
        ->and(Comissao::cents($consumoRealizado))->toBeLessThan(Comissao::cents($fatura));
});

it('nao paga comissao sobre consumo zero ou negativo', function () {
    expect(Comissao::cents(0))->toBe(0)
        ->and(Comissao::cents(-500))->toBe(0);
});

it('arredonda o centavo de forma estavel', function () {
    // 10% de R$ 1,55 sao 15,5 centavos.
    expect(Comissao::cents(155))->toBe(16)
        ->and(Comissao::cents(155))->toBe(Comissao::cents(155));
});

it('divide a adesao ao meio entre vendedor e Avalia', function () {
    expect(Comissao::parteAdesaoCents(1_200_000))->toBe(600_000)
        ->and(Comissao::parteAdesaoCents(0))->toBe(0);
});

it('nao gera parte de adesao isentada', function () {
    // Isentar zera os dois lados, nao so o da empresa.
    expect(Comissao::parteAdesaoCents(0))->toBe(0);
});
