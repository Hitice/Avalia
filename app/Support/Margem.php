<?php

namespace App\Support;

/**
 * Margem da Avalia sobre um preco de venda.
 *
 *     margem = venda - custo do fornecedor - imposto sobre a venda
 *
 * Tudo em centavos inteiros, aliquota em pontos-base. Nunca vai para tela de
 * cliente nem de vendedor: margem e custo sao internos (PDD.md, secao 6).
 */
final class Margem
{
    /** Teto de sanidade: aliquota igual ou maior que 100% nao e imposto, e erro. */
    public const BPS_MAXIMO = 9_999;

    /** Quanto da venda vai embora em imposto. */
    public static function impostoCents(int $vendaCents, int $impostoBps): int
    {
        return (int) round($vendaCents * self::bps($impostoBps) / 10_000);
    }

    /**
     * Sobra depois do fornecedor e do fisco, ou null enquanto o custo nao
     * estiver cadastrado. Pode ser negativa, e e justamente isso que a tela
     * precisa mostrar.
     */
    public static function liquidaCents(int $vendaCents, ?int $custoCents, int $impostoBps): ?int
    {
        if ($custoCents === null) {
            return null;
        }

        return $vendaCents - $custoCents - self::impostoCents($vendaCents, $impostoBps);
    }

    /** Margem em porcentagem do preco de venda, com uma casa decimal. */
    public static function pct(int $vendaCents, ?int $custoCents, int $impostoBps): ?float
    {
        $liquida = self::liquidaCents($vendaCents, $custoCents, $impostoBps);

        if ($liquida === null || $vendaCents === 0) {
            return null;
        }

        return round($liquida * 100 / $vendaCents, 1);
    }

    /**
     * Menor preco que ainda nao da prejuizo.
     *
     *     venda - custo - venda × aliquota = 0
     *     venda = custo ÷ (1 - aliquota)
     *
     * Arredonda para cima de proposito: um centavo a menos ja e venda no
     * negativo, e piso que permite prejuizo nao e piso.
     */
    public static function pisoCents(?int $custoCents, int $impostoBps): ?int
    {
        if ($custoCents === null) {
            return null;
        }

        $restante = 10_000 - self::bps($impostoBps);
        $piso = (int) ceil($custoCents * 10_000 / $restante);

        // A formula trata o imposto como continuo, mas ele e arredondado ao
        // centavo, o que pode deixar o piso um centavo acima do necessario.
        // Desce enquanto o centavo anterior ainda nao der prejuizo, para o piso
        // ser exatamente o menor preco que se paga.
        while ($piso > 0 && ! self::daPrejuizo($piso - 1, $custoCents, $impostoBps)) {
            $piso--;
        }

        return $piso;
    }

    /** A venda cobre custo e imposto? Sem custo cadastrado, nao da para saber. */
    public static function daPrejuizo(int $vendaCents, ?int $custoCents, int $impostoBps): bool
    {
        $liquida = self::liquidaCents($vendaCents, $custoCents, $impostoBps);

        return $liquida !== null && $liquida < 0;
    }

    private static function bps(int $impostoBps): int
    {
        return max(0, min($impostoBps, self::BPS_MAXIMO));
    }
}
