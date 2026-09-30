<?php

namespace App\Support;

/**
 * Comissao do vendedor sobre o LUCRO do mes, e nao sobre faturamento.
 *
 * Cada real de consumo carrega o custo do fornecedor, entao comissionar
 * faturamento pagaria igual por uma venda que rende e por uma que sangra. Sobre
 * lucro, o interesse do vendedor e o mesmo da casa.
 *
 * Aliquota unica para todo plano e toda faixa; nao mora no Plano porque e
 * parametro comercial da Avalia, e nao atributo de plano (PDD.md, secao 5).
 *
 * O lucro vem de `Margem::baseComissaoCents`, e tem de vir de la: duas contas
 * para a mesma comissao viram divergencia no primeiro repasse.
 */
final class Comissao
{
    /**
     * Aliquota unica, em pontos percentuais.
     *
     * Nao ha adicional por excedente. Ele existia quando a base era faturamento;
     * sobre lucro o efeito ja acontece sozinho, e dobrar em cima pagaria o mesmo
     * ganho duas vezes.
     */
    public const PCT_PADRAO = 10;

    /** Teto de sanidade: acima disso a comissao come a operacao. */
    public const PCT_MAXIMO = 50;

    /**
     * Aliquota valida, com o padrao para quem nao tem taxa propria.
     *
     * A administracao negocia caso a caso. Fora da faixa vale o padrao, porque
     * taxa invalida no cadastro nao pode virar repasse errado no fechamento.
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
     * Mes no prejuizo da zero, e nao comissao negativa: o vendedor nao ganha
     * sobre lucro que nao houve, mas tambem nao paga por ter vendido.
     */
    public static function cents(int $lucroCents, ?int $pct = null): int
    {
        if ($lucroCents <= 0) {
            return 0;
        }

        // round, nao trunca: dois calculos do mesmo mes dao o mesmo centavo.
        return (int) round($lucroCents * self::pct($pct) / 100);
    }

    /**
     * Parte do vendedor na taxa de adesao: metade, e a outra e da casa.
     *
     * Isentar a adesao zera as duas, e nao so a da empresa.
     */
    public static function parteAdesaoCents(int $adesaoCents): int
    {
        return (int) round(max(0, $adesaoCents) / 2);
    }
}
