<?php

namespace App\Support;

/**
 * Margem liquida da Avalia sobre um preco de venda.
 *
 *     margem = venda - custo do fornecedor - imposto - comissao do vendedor
 *
 * A comissao entra como custo porque e isso que ela e: sai do mesmo preco.
 * Margem calculada sem ela mente em 10 pontos, e a decisao comercial e ganhar
 * 30% liquidos DEPOIS de pagar o vendedor.
 *
 * A comissao incide sobre a venda MENOS o imposto, nao sobre a venda cheia: o
 * vendedor comissiona sobre o que a Avalia recebe de fato. Assim a parte
 * proporcional do imposto sai da comissao, e nao da margem da operacao.
 *
 * Tudo em centavos inteiros, aliquotas em pontos-base. Nunca vai para tela de
 * cliente nem de vendedor: margem e custo sao internos (PDD.md, secao 6).
 */
final class Margem
{
    /** Teto de sanidade: soma de aliquotas em 100% nao deixa sobra nenhuma. */
    public const BPS_MAXIMO = 9_999;

    /** Quanto da venda vai embora em imposto. */
    public static function impostoCents(int $vendaCents, int $impostoBps): int
    {
        return (int) round($vendaCents * self::bps($impostoBps) / 10_000);
    }

    /** O que a Avalia recebe de fato, e sobre o que o vendedor comissiona. */
    public static function baseComissaoCents(int $vendaCents, int $impostoBps): int
    {
        return $vendaCents - self::impostoCents($vendaCents, $impostoBps);
    }

    /** Quanto da venda vai embora em comissao do vendedor. */
    public static function comissaoCents(int $vendaCents, int $comissaoBps, int $impostoBps = 0): int
    {
        return (int) round(self::baseComissaoCents($vendaCents, $impostoBps) * self::bps($comissaoBps) / 10_000);
    }

    /**
     * Aliquota de comissao medida sobre a venda cheia.
     *
     * 10% sobre o liquido de 8,60% de imposto custa 9,14% da venda. E este o
     * numero que entra na formula do preco alvo, que raciocina em fracao da
     * venda.
     */
    public static function comissaoEfetivaBps(int $comissaoBps, int $impostoBps): int
    {
        return (int) round(self::bps($comissaoBps) * (10_000 - self::bps($impostoBps)) / 10_000);
    }

    /**
     * Sobra depois do fornecedor, do fisco e do vendedor, ou null enquanto o
     * custo nao estiver cadastrado. Pode ser negativa, e e justamente isso que
     * a tela precisa mostrar.
     */
    public static function liquidaCents(int $vendaCents, ?int $custoCents, int $impostoBps, int $comissaoBps = 0): ?int
    {
        if ($custoCents === null) {
            return null;
        }

        return $vendaCents
            - $custoCents
            - self::impostoCents($vendaCents, $impostoBps)
            - self::comissaoCents($vendaCents, $comissaoBps, $impostoBps);
    }

    /** Margem em porcentagem do preco de venda, com uma casa decimal. */
    public static function pct(int $vendaCents, ?int $custoCents, int $impostoBps, int $comissaoBps = 0): ?float
    {
        $liquida = self::liquidaCents($vendaCents, $custoCents, $impostoBps, $comissaoBps);

        if ($liquida === null || $vendaCents === 0) {
            return null;
        }

        return round($liquida * 100 / $vendaCents, 1);
    }

    /**
     * Menor preco que ainda nao da prejuizo.
     *
     *     venda - custo - imposto - comissao = 0
     */
    public static function pisoCents(?int $custoCents, int $impostoBps, int $comissaoBps = 0): ?int
    {
        return self::precoAlvoCents($custoCents, $impostoBps, $comissaoBps, 0);
    }

    /**
     * Preco que entrega a margem pedida.
     *
     *     venda = custo ÷ (1 - imposto - comissao efetiva - margem)
     *
     * Arredonda para cima: um centavo a menos ja fica abaixo do alvo, e alvo
     * que nao se atinge nao e alvo.
     */
    public static function precoAlvoCents(?int $custoCents, int $impostoBps, int $comissaoBps, int $margemBps): ?int
    {
        if ($custoCents === null) {
            return null;
        }

        // A formula raciocina em fracao da venda, entao a comissao entra pela
        // aliquota efetiva. O bps() sobre a soma garante que sobre pelo menos
        // um ponto-base e a divisao nao estoure.
        $comissao = self::comissaoEfetivaBps($comissaoBps, $impostoBps);
        $restante = 10_000 - self::bps($impostoBps + $comissao + max(0, $margemBps));
        $alvo = (int) ceil($custoCents * 10_000 / $restante);

        // A formula trata as aliquotas como continuas, mas imposto e comissao
        // sao arredondados ao centavo. O candidato pode ficar um ou dois
        // centavos de fora nos dois sentidos, entao sobe ate servir e depois
        // desce ate o menor que ainda serve. A folga de 8 centavos e teto de
        // seguranca: na pratica a correcao nunca passa de dois.
        for ($i = 0; $i < 8 && ! self::atinge($alvo, $custoCents, $impostoBps, $comissaoBps, $margemBps); $i++) {
            $alvo++;
        }

        while ($alvo > 0 && self::atinge($alvo - 1, $custoCents, $impostoBps, $comissaoBps, $margemBps)) {
            $alvo--;
        }

        return $alvo;
    }

    /** A venda cobre custo, imposto e comissao? Sem custo, nao da para saber. */
    public static function daPrejuizo(int $vendaCents, ?int $custoCents, int $impostoBps, int $comissaoBps = 0): bool
    {
        $liquida = self::liquidaCents($vendaCents, $custoCents, $impostoBps, $comissaoBps);

        return $liquida !== null && $liquida < 0;
    }

    /** A venda entrega pelo menos a margem pedida? */
    public static function atinge(int $vendaCents, ?int $custoCents, int $impostoBps, int $comissaoBps, int $margemBps): bool
    {
        $liquida = self::liquidaCents($vendaCents, $custoCents, $impostoBps, $comissaoBps);

        if ($liquida === null) {
            return false;
        }

        return $liquida * 10_000 >= $vendaCents * max(0, $margemBps);
    }

    private static function bps(int $bps): int
    {
        return max(0, min($bps, self::BPS_MAXIMO));
    }
}
