<?php

namespace App\Support;

/**
 * Comissao do vendedor sobre o consumo do cliente.
 *
 * Aliquota unica para todo plano e toda faixa: 10% sobre o que o cliente
 * efetivamente consumiu no mes. Nao mora no Plano justamente porque nao e
 * atributo de plano nenhum, e sim parametro comercial da Avalia (PDD.md, secao 5).
 *
 * A base e o consumo REALIZADO, nao o valor faturado. Cliente com minimo de
 * R$ 900 que consome R$ 300 paga R$ 979,90 e gera R$ 30,00 de comissao: o piso
 * da fatura protege a Avalia, nao a comissao do vendedor.
 *
 * E o consumo LIQUIDO DE IMPOSTO. O vendedor comissiona sobre o que a Avalia
 * recebe de fato, entao a parte proporcional do imposto sai da comissao e nao
 * da margem da operacao. A aliquota vem do catalogo, porque muda com a faixa de
 * faturamento da empresa.
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

    /** Comissao em centavos sobre o consumo realizado na competencia. */
    public static function cents(int $consumoRealizadoCents, bool $houveExcedente = false, int $impostoBps = 0): int
    {
        if ($consumoRealizadoCents <= 0) {
            return 0;
        }

        $base = Margem::baseComissaoCents($consumoRealizadoCents, $impostoBps);

        // round e nao trunca: sempre a favor de ninguem em particular, mas
        // estavel: dois calculos do mesmo mes dao o mesmo centavo.
        return (int) round($base * self::pct($houveExcedente) / 100);
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
