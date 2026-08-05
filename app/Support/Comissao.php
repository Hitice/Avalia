<?php

namespace App\Support;

/**
 * Comissao do vendedor sobre o LUCRO do mes.
 *
 * Aliquota unica para todo plano e toda faixa: 10% do que sobrar depois do
 * imposto e do custo do fornecedor. Nao mora no Plano justamente porque nao e
 * atributo de plano nenhum, e sim parametro comercial da Avalia (PDD.md, secao 5).
 *
 * A base e lucro, e nao faturamento. Cada real de consumo carrega o custo do
 * fornecedor junto, entao comissionar sobre faturamento pagaria igual por uma
 * venda que rende e por uma que sangra. Sobre lucro, o interesse do vendedor e
 * o mesmo da Avalia.
 *
 * Quem calcula o lucro e Margem::baseComissaoCents, e e de la que este valor
 * tem de vir: duas contas diferentes para a mesma comissao viram divergencia no
 * primeiro repasse.
 */
final class Comissao
{
    /** Aliquota normal, em pontos percentuais. */
    public const PCT_PADRAO = 10;

    /**
     * Aliquota do mes em que o cliente estourou a franquia.
     *
     * Sao 10 pontos a mais e valem para o plano inteiro naquela competencia,
     * nao so para a parcela excedente.
     */
    public const PCT_COM_EXCEDENTE = 20;

    public static function pct(bool $houveExcedente = false): int
    {
        return $houveExcedente ? self::PCT_COM_EXCEDENTE : self::PCT_PADRAO;
    }

    /**
     * Comissao em centavos sobre o lucro da competencia.
     *
     * Mes no prejuizo nao gera comissao, e nao comissao negativa: o vendedor
     * nao ganha sobre lucro que nao existiu, mas tambem nao paga para ter
     * vendido.
     */
    public static function cents(int $lucroCents, bool $houveExcedente = false): int
    {
        if ($lucroCents <= 0) {
            return 0;
        }

        // round e nao trunca: sempre a favor de ninguem em particular, mas
        // estavel: dois calculos do mesmo mes dao o mesmo centavo.
        return (int) round($lucroCents * self::pct($houveExcedente) / 100);
    }

    /**
     * Parte do vendedor na taxa de adesao: metade.
     *
     * A outra metade e da Avalia. Isentar a adesao zera as duas, nao so a da
     * empresa.
     */
    public static function parteAdesaoCents(int $adesaoCents): int
    {
        return (int) round(max(0, $adesaoCents) / 2);
    }
}
