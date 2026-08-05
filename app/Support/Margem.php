<?php

namespace App\Support;

/**
 * Margem liquida da Avalia sobre um preco de venda.
 *
 *     lucro   = venda - imposto - custo do fornecedor
 *     comissao = 10% do lucro
 *     margem  = lucro - comissao
 *
 * A comissao incide sobre o LUCRO, nao sobre o faturamento. O imposto sai
 * primeiro, o fornecedor sai em seguida, e o vendedor leva 10% do que sobrar.
 * E isso que alinha o incentivo: venda de margem apertada rende menos comissao,
 * em vez de render igual e sangrar a operacao.
 *
 * A consequencia aritmetica de comissionar sobre lucro e que a Avalia fica
 * sempre com 90% dele, qualquer que seja o preco. Margem alvo de 30% da venda,
 * entao, quer dizer 30% depois desses 90%.
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

    /**
     * O lucro antes da comissao, que e a base sobre a qual o vendedor ganha.
     *
     * Null enquanto o custo do fornecedor nao estiver cadastrado: sem ele nao
     * ha lucro conhecido, e comissao chutada vira divergencia no repasse.
     */
    public static function baseComissaoCents(int $vendaCents, ?int $custoCents, int $impostoBps): ?int
    {
        if ($custoCents === null) {
            return null;
        }

        return $vendaCents - self::impostoCents($vendaCents, $impostoBps) - $custoCents;
    }

    /**
     * Quanto do lucro vai para o vendedor.
     *
     * Venda no prejuizo nao gera comissao nenhuma, e nao comissao negativa: o
     * vendedor nao ganha sobre lucro que nao existiu, mas tambem nao paga para
     * ter vendido.
     */
    public static function comissaoCents(int $vendaCents, ?int $custoCents, int $comissaoBps, int $impostoBps): ?int
    {
        $lucro = self::baseComissaoCents($vendaCents, $custoCents, $impostoBps);

        if ($lucro === null) {
            return null;
        }

        return $lucro <= 0 ? 0 : (int) round($lucro * self::bps($comissaoBps) / 10_000);
    }

    /**
     * Sobra depois do fornecedor, do fisco e do vendedor, ou null enquanto o
     * custo nao estiver cadastrado. Pode ser negativa, e e justamente isso que
     * a tela precisa mostrar.
     */
    public static function liquidaCents(int $vendaCents, ?int $custoCents, int $impostoBps, int $comissaoBps = 0): ?int
    {
        $lucro = self::baseComissaoCents($vendaCents, $custoCents, $impostoBps);

        if ($lucro === null) {
            return null;
        }

        return $lucro - self::comissaoCents($vendaCents, $custoCents, $comissaoBps, $impostoBps);
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
     * Comissionando sobre lucro, a Avalia fica com (1 - comissao) do lucro:
     *
     *     (1 - k) × (venda × (1 - imposto) - custo) = margem × venda
     *
     * que resolvido em venda da
     *
     *     venda = custo × (1 - k) ÷ [ (1 - k) × (1 - imposto) - margem ]
     *
     * Arredonda para cima: um centavo a menos ja fica abaixo do alvo, e alvo
     * que nao se atinge nao e alvo.
     */
    public static function precoAlvoCents(?int $custoCents, int $impostoBps, int $comissaoBps, int $margemBps): ?int
    {
        if ($custoCents === null) {
            return null;
        }

        $sobraDaComissao = 10_000 - self::bps($comissaoBps);          // (1 - k)
        $sobraDoImposto = 10_000 - self::bps($impostoBps);            // (1 - imposto)

        // Denominador em pontos-base. Se nao sobra nada, nenhum preco atinge o
        // alvo: devolve null em vez de dividir por zero e mentir um numero.
        $restante = intdiv($sobraDaComissao * $sobraDoImposto, 10_000) - max(0, $margemBps);

        if ($restante <= 0) {
            return null;
        }

        $alvo = (int) ceil($custoCents * $sobraDaComissao / $restante);

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
