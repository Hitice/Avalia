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
    /**
     * Aliquota unica, em pontos percentuais.
     *
     * Nao ha adicional por excedente. Ele existia quando a comissao lia
     * faturamento e servia para o vendedor ganhar quando o cliente consumia
     * acima da franquia. Comissionando sobre lucro isso ja acontece sozinho:
     * consumo a mais gera lucro a mais, e 10% dele tambem. Dobrar a aliquota
     * em cima pagaria o mesmo ganho duas vezes.
     */
    public const PCT_PADRAO = 10;

    /** Teto de sanidade: comissao acima disso comeria a operacao inteira. */
    public const PCT_MAXIMO = 50;

    /**
     * Aliquota valida, com o padrao para quem nao tem taxa propria.
     *
     * A administracao negocia caso a caso, entao cada vendedor pode ter a sua.
     * Fora da faixa, vale o padrao: taxa invalida no cadastro nao pode virar
     * repasse errado no fechamento.
     */
    public static function pct(?int $doVendedor = null): int
    {
        if ($doVendedor === null || $doVendedor < 0 || $doVendedor > self::PCT_MAXIMO) {
            return self::PCT_PADRAO;
        }

        return $doVendedor;
    }

    /**
     * Comissao em centavos sobre o lucro da competencia.
     *
     * Mes no prejuizo nao gera comissao, e nao comissao negativa: o vendedor
     * nao ganha sobre lucro que nao existiu, mas tambem nao paga para ter
     * vendido.
     */
    public static function cents(int $lucroCents, ?int $pct = null): int
    {
        if ($lucroCents <= 0) {
            return 0;
        }

        // round e nao trunca: sempre a favor de ninguem em particular, mas
        // estavel: dois calculos do mesmo mes dao o mesmo centavo.
        return (int) round($lucroCents * self::pct($pct) / 100);
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
