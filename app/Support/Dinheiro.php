<?php

namespace App\Support;

/**
 * Dinheiro em centavos inteiros, do banco ate a tela.
 *
 * Float nao entra nesta aplicacao: 0.1 + 0.2 nao da 0.3 em ponto flutuante, e
 * num sistema de cobranca isso vira divergencia de centavo em fatura, o tipo
 * de erro que so aparece depois do cliente reclamar.
 */
final class Dinheiro
{
    /** Formata centavos como moeda: 90000 -> "R$ 900,00" */
    public static function brl(int $centavos): string
    {
        // Espaco nao-quebravel entre o simbolo e o numero: impede que a quebra
        // de linha separe "R$" do valor no meio de uma tabela.
        return 'R$'."\u{00A0}".number_format($centavos / 100, 2, ',', '.');
    }

    /**
     * Numero sem simbolo, para preencher campo de formulario: 7990 -> "79,90".
     *
     * Nao usa espaco nem "R$" porque o valor volta por paraCentavos() no
     * submit, e o campo deve mostrar exatamente o que o operador digitaria.
     */
    public static function numero(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }

    /**
     * Le o que o usuario digitou e devolve centavos.
     *
     * Aceita "1.234,56", "1234,56", "1234.56" e "1234". A regra: se houver
     * virgula, ela e o separador decimal e o ponto e milhar (padrao pt-BR).
     * Sem virgula, um ponto com exatamente duas casas depois e decimal.
     */
    public static function paraCentavos(string|int|float|null $entrada): ?int
    {
        if ($entrada === null || $entrada === '') {
            return null;
        }

        if (is_int($entrada)) {
            return $entrada * 100;
        }

        $texto = trim((string) $entrada);
        $texto = preg_replace('/[^\d,.\-]/', '', $texto) ?? '';

        if ($texto === '' || $texto === '-') {
            return null;
        }

        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (preg_match('/\.\d{3}$/', $texto) && substr_count($texto, '.') >= 1) {
            // "1.234" sem virgula: ponto de milhar, nao decimal.
            $texto = str_replace('.', '', $texto);
        }

        // round antes do cast: (int)(19.99 * 100) da 1998 em ponto flutuante.
        return (int) round(((float) $texto) * 100);
    }
}
