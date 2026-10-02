<?php

namespace App\Support;

use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Staff;

/**
 * O dinheiro do Avalia One, num lugar so: visao geral e financeiro leem daqui.
 *
 * A mesma cifra calculada em duas telas diverge no primeiro ajuste, e ninguem
 * descobre pela tela, descobre pelo repasse errado. Ja aconteceu com a comissao,
 * bruta no painel e liquida na carteira do vendedor.
 *
 * NAO sabe saldo de caixa de verdade: falta o pagamento de saida. Isso agora e
 * pergunta do razao, e a PDD (secao 15) diz quando esta classe sai.
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
     * Liquida das demonstracoes, como a carteira mostra: o custo da demonstracao
     * sai da comissao dele. Consulta da administracao nao entra, porque nao ha
     * comissao de onde descontar.
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
