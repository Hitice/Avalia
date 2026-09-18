<?php

use App\Support\Cascata;

/**
 * A divisao de uma venda entre quem vendeu e quem esta acima dele.
 *
 * O que estes testes guardam e o que o provedor recusaria: soma acima de 100%,
 * beneficiario sem carteira, e a sobra da divisao sumindo. As tres quebram a
 * cobranca inteira, e a terceira quebra em silencio.
 */
it('reparte a venda pela linha inteira', function () {
    $divisao = Cascata::repartir([
        ['wallet' => 'w-vendedor', 'bps' => 3000, 'nome' => 'Vendedor'],
        ['wallet' => 'w-gerente', 'bps' => 1000, 'nome' => 'Gerente'],
        ['wallet' => 'w-topo', 'bps' => 5000, 'nome' => 'Topo'],
    ]);

    expect($divisao['beneficiarios'])->toHaveCount(3)
        ->and($divisao['beneficiarios'][0]['percentual'])->toBe(30.0)
        ->and($divisao['beneficiarios'][2]['percentual'])->toBe(50.0)
        // Sobra 10% para a casa, que e quem paga a taxa do provedor.
        ->and($divisao['casa_bps'])->toBe(1000);
});

it('recusa a venda quando a linha soma mais de cem por cento', function () {
    // O provedor recusaria a cobranca inteira. Melhor falhar aqui, com o nome
    // do problema, do que na recusa dele com o cliente esperando na tela.
    expect(fn () => Cascata::repartir([
        ['wallet' => 'w-1', 'bps' => 6000, 'nome' => 'Um'],
        ['wallet' => 'w-2', 'bps' => 5000, 'nome' => 'Dois'],
    ]))->toThrow(RuntimeException::class, 'acima de 100%');
});

it('pula quem ainda nao tem carteira, em vez de derrubar a venda', function () {
    // Parceiro que nao terminou o cadastro nao pode impedir a venda de quem
    // esta embaixo dele. O percentual dele fica com a casa.
    $divisao = Cascata::repartir([
        ['wallet' => 'w-vendedor', 'bps' => 3000, 'nome' => 'Vendedor'],
        ['wallet' => '', 'bps' => 1000, 'nome' => 'Sem carteira'],
        ['wallet' => 'w-topo', 'bps' => 5000, 'nome' => 'Topo'],
    ]);

    expect($divisao['beneficiarios'])->toHaveCount(2)
        ->and($divisao['casa_bps'])->toBe(2000);
});

it('monta o split no formato que o provedor espera', function () {
    $split = Cascata::split([
        ['wallet' => 'w-vendedor', 'bps' => 2550, 'nome' => 'Vendedor'],
        ['wallet' => 'w-topo', 'bps' => 5000, 'nome' => 'Topo'],
    ]);

    expect($split)->toBe([
        ['walletId' => 'w-vendedor', 'percentualValue' => 25.5],
        ['walletId' => 'w-topo', 'percentualValue' => 50.0],
    ]);
});

it('nao perde centavo na divisao', function () {
    // 33,33% de 10.001 nao e inteiro. A sobra fica com a casa, e a soma tem
    // que fechar exatamente com o liquido recebido.
    $divisao = Cascata::emCentavos([
        ['wallet' => 'w-1', 'bps' => 3333, 'nome' => 'Um'],
        ['wallet' => 'w-2', 'bps' => 3333, 'nome' => 'Dois'],
    ], liquidoCents: 10001);

    $distribuido = array_sum(array_column($divisao['beneficiarios'], 'valor_cents'));

    expect($distribuido + $divisao['casa_cents'])->toBe(10001);
});

it('deixa a casa com tudo quando nao ha rede', function () {
    $divisao = Cascata::repartir([]);

    expect($divisao['beneficiarios'])->toBe([])
        ->and($divisao['casa_bps'])->toBe(10000);
});
