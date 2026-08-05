<?php

use App\Support\Comissao;
use App\Support\Margem;

it('paga 10% sobre o lucro do mes', function () {
    expect(Comissao::pct())->toBe(10)
        ->and(Comissao::cents(30_000))->toBe(3_000)
        ->and(Comissao::cents(38_591))->toBe(3_859);
});

it('tem aliquota unica, sem adicional por excedente', function () {
    // O adicional existia quando a comissao lia faturamento. Sobre lucro ele
    // pagaria duas vezes: consumo a mais ja gera lucro a mais, e 10% dele.
    expect(Comissao::cents(60_000))->toBe(Comissao::cents(30_000) * 2);
});

it('le lucro, e nao faturamento', function () {
    // Duas vendas do mesmo tamanho pagam comissao diferente quando o custo do
    // fornecedor e diferente. E isso que alinha o vendedor a operacao.
    $magra = Margem::baseComissaoCents(1_000, 800, 1_350);
    $gorda = Margem::baseComissaoCents(1_000, 300, 1_350);

    expect(Comissao::cents($magra))->toBeLessThan(Comissao::cents($gorda));
});

it('nao paga comissao sobre lucro zero ou prejuizo', function () {
    // O vendedor nao ganha sobre lucro que nao existiu, mas tambem nao paga
    // para ter vendido.
    expect(Comissao::cents(0))->toBe(0)
        ->and(Comissao::cents(-500))->toBe(0);
});

it('arredonda o centavo de forma estavel', function () {
    // 10% de R$ 1,55 sao 15,5 centavos.
    expect(Comissao::cents(155))->toBe(16)
        ->and(Comissao::cents(155))->toBe(Comissao::cents(155));
});

it('usa a mesma conta de lucro que a tela mostra', function () {
    // Duas contas diferentes para a mesma comissao viram divergencia no
    // primeiro repasse, e o vendedor descobre antes da Avalia.
    $lucro = Margem::baseComissaoCents(528, 280, 1_350);

    expect(Comissao::cents($lucro))->toBe(Margem::comissaoCents(528, 280, 1_000, 1_350));
});

it('divide a adesao ao meio entre vendedor e Avalia', function () {
    expect(Comissao::parteAdesaoCents(1_200_000))->toBe(600_000)
        ->and(Comissao::parteAdesaoCents(0))->toBe(0);
});

it('nao gera parte de adesao isentada', function () {
    // Isentar zera os dois lados, nao so o da empresa.
    expect(Comissao::parteAdesaoCents(0))->toBe(0);
});
