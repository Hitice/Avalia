<?php

namespace App\Support;

use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Staff;

/**
 * O dinheiro da operacao, num lugar so.
 *
 * Existe por um motivo que ja custou caro neste projeto: a mesma cifra
 * calculada em duas telas diverge no primeiro ajuste, e ninguem descobre pela
 * tela, descobre pelo repasse errado. Foi o que aconteceu com a comissao, que
 * o painel mostrava bruta enquanto a carteira do vendedor mostrava liquida.
 *
 * Entao a visao geral e o financeiro leem daqui. Se um numero mudar, muda para
 * os dois ao mesmo tempo.
 *
 * O que este arquivo NAO sabe: saldo de caixa de verdade. O sistema registra o
 * que foi cobrado e o que entrou, mas nao registra pagamento de saida (custo ao
 * fornecedor, imposto recolhido, comissao efetivamente paga). Saldo com metade
 * das saidas seria numero inventado, e numero inventado em tela de dinheiro e
 * pior do que numero ausente.
 */
final class Caixa
{
    /**
     * O que entrou no mes corrente.
     *
     * Conta pela data da BAIXA, e nao pela competencia: o mes do dinheiro e o
     * mes em que ele chegou, mesmo que a fatura seja de um periodo anterior.
     * E a diferenca entre saber o quanto se faturou e saber o quanto se tem.
     */
    public static function recebidoNoMesCents(): int
    {
        return (int) Fatura::whereNotNull('liquidada_em')
            ->whereBetween('liquidada_em', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_cents');
    }

    /**
     * A comissao ja liberada que ainda pertence aos vendedores.
     *
     * Liquida das demonstracoes, como a carteira do vendedor mostra: o custo da
     * demonstracao sai da comissao dele, e ignorar isso aqui recriaria
     * exatamente a divergencia que este arquivo existe para impedir.
     *
     * Comissao de consulta feita pela administracao nao entra: nao ha comissao
     * de onde descontar, e o custo dela ja pesa no custo do periodo.
     */
    public static function aRepassarCents(): int
    {
        $liberada = (int) Fatura::whereNotNull('comissao_liberada_em')->sum('comissao_cents');

        $demonstracoes = (int) Consulta::query()
            ->whereIn('vendedor_id', Staff::query()->where('papel', 'vendedor')->select('id'))
            ->where('situacao', Consulta::SUCESSO)
            ->sum('custo_cents');

        // Nunca negativo: vendedor que demonstrou mais do que vendeu nao deve
        // dinheiro a casa, so nao tem repasse a receber.
        return max(0, $liberada - $demonstracoes);
    }

    /**
     * Os totais de fatura por situacao, para os cartoes do topo.
     *
     * @return array{a_receber: int, vencido: int, liquidado: int}
     */
    public static function totais(): array
    {
        $soma = fn (array $situacoes) => (int) Fatura::whereIn('situacao_pagamento', $situacoes)->sum('total_cents');

        return [
            'a_receber' => $soma([Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO]),
            'vencido' => $soma([Fatura::PAGAMENTO_VENCIDO]),
            'liquidado' => $soma([Fatura::PAGAMENTO_LIQUIDADO]),
        ];
    }
}
