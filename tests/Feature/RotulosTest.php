<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Fatura;
use App\Support\Rotulos;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Nenhuma tela imprime o valor gravado no banco.
 *
 * As situacoes vinham direto da coluna: "liquidado", "vencido", "inadimplente",
 * em minusculas. Sao nomes escolhidos para o codigo. O cliente que abre a fatura
 * precisa ler "Paga", e o operador que olha a carteira precisa ler "Suspensa por
 * debito", nao o valor que o desenvolvedor digitou na migracao.
 *
 * O teste que importa aqui e o primeiro: situacao nova sem rotulo cai, em vez de
 * aparecer crua na tela de alguem.
 */
it('da nome de negocio a toda situacao de fatura', function () {
    foreach (Fatura::SITUACOES_PAGAMENTO as $situacao) {
        expect(Rotulos::fatura($situacao))
            ->not->toBe($situacao)
            ->not->toBe('Não definida')
            ->and(Rotulos::faturaEtiqueta($situacao))->toStartWith('etiqueta-');
    }
});

it('da nome de negocio a toda situacao de empresa', function () {
    $situacoes = Rotulos::situacoesDaEmpresa();

    expect(array_keys($situacoes))->toBe(['ativo', 'inadimplente', 'bloqueado', 'inativo']);

    foreach ($situacoes as $valor => $rotulo) {
        expect($rotulo)->not->toBe($valor)
            ->and(Rotulos::empresaEtiqueta($valor))->toStartWith('etiqueta-');
    }
});

it('nao inventa nome para situacao desconhecida', function () {
    // Coluna com valor que ninguem previu nao pode virar tela em branco nem
    // erro: aparece como indefinida e alguem investiga.
    expect(Rotulos::fatura('ainda_nao_existe'))->toBe('Não definida')
        ->and(Rotulos::empresa(null))->toBe('Não definida');
});

it('mede a espera do cliente em segundos', function () {
    // O banco guarda milissegundos porque e o que o fornecedor devolve. Ninguem
    // decide nada com "847 ms".
    expect(Rotulos::espera(847))->toBe('0,8 s')
        ->and(Rotulos::espera(12_400))->toBe('12,4 s')
        ->and(Rotulos::espera(null))->toBe('Sem registro');
});

it('mostra ao cliente a fatura como paga, e nao como liquidada', function () {
    $empresa = empresaComPlano();

    app(FecharCompetencia::class)($empresa, '2026-07');

    comoEmpresa($empresa)->get(route('empresa.faturas'))->assertOk()->assertSee('Em aberto');

    Fatura::first()->update(['situacao_pagamento' => Fatura::PAGAMENTO_LIQUIDADO]);

    comoEmpresa($empresa)->get(route('empresa.faturas'))->assertOk()->assertSee('Paga');
});
