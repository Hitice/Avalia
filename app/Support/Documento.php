<?php

namespace App\Support;

/**
 * CNPJ da empresa contratante: normaliza, valida e formata.
 *
 * Desde julho de 2026 o CNPJ pode ser alfanumerico: as doze primeiras posicoes
 * aceitam letras, e so os dois digitos verificadores continuam numericos. O
 * calculo do verificador passou a usar o valor ASCII do caractere menos 48, o
 * que faz o algoritmo antigo virar caso particular do novo ('0' tem ASCII 48,
 * entao vale 0). Por isso ha um metodo so, e nao dois.
 *
 * Validar o verificador nao prova que a empresa existe, so que ninguem digitou
 * um numero inventado. Conferir situacao cadastral e outra historia, e depende
 * de consultar a Receita.
 */
final class Documento
{
    /** Peso de cada posicao, da direita para a esquerda, na conta do verificador. */
    private const PESOS = [2, 3, 4, 5, 6, 7, 8, 9];

    /**
     * Deixa so o que o CNPJ aceita: digito e letra maiuscula.
     *
     * Guardar normalizado e o que permite comparar "12.345.678/0001-95" com
     * "12345678000195" e concluir que sao a mesma empresa.
     */
    public static function normalizarCnpj(?string $entrada): string
    {
        return preg_replace('/[^0-9A-Z]/', '', mb_strtoupper(trim((string) $entrada))) ?? '';
    }

    public static function cnpjValido(?string $entrada): bool
    {
        $cnpj = self::normalizarCnpj($entrada);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Os verificadores sao sempre numericos, mesmo no CNPJ alfanumerico.
        if (! ctype_digit(substr($cnpj, 12, 2))) {
            return false;
        }

        // Documento de caractere repetido passa na conta do verificador mas nao
        // existe na Receita. E o erro de digitacao mais comum que sobra.
        if (preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        $base = substr($cnpj, 0, 12);

        return $cnpj === $base.self::verificadores($base);
    }

    /** Devolve os dois digitos verificadores de uma base de doze posicoes. */
    public static function verificadores(string $base): string
    {
        $primeiro = self::digito($base);
        $segundo = self::digito($base.$primeiro);

        return $primeiro.$segundo;
    }

    /** 12345678000195 -> "12.345.678/0001-95" */
    /**
     * Documento com o miolo escondido, para tela e PDF compartilhavel.
     *
     * Mostra o comeco e o fim, que bastam para a pessoa reconhecer o proprio
     * numero, e esconde o resto: o arquivo circula, e documento inteiro em
     * arquivo que circula e dado pessoal fora de controle.
     */
    public static function mascarar(?string $entrada): string
    {
        $digitos = preg_replace('/\D/', '', (string) $entrada) ?? '';

        if (strlen($digitos) < 6) {
            return $digitos === '' ? '' : str_repeat('*', strlen($digitos));
        }

        return substr($digitos, 0, 3).str_repeat('*', strlen($digitos) - 5).substr($digitos, -2);
    }

    public static function formatarCnpj(?string $entrada): string
    {
        $cnpj = self::normalizarCnpj($entrada);

        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return vsprintf('%s.%s.%s/%s-%s', [
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2),
        ]);
    }

    /**
     * Modulo 11 sobre o valor ASCII menos 48 de cada caractere, com os pesos
     * ciclando de 2 a 9 da direita para a esquerda. Resto menor que 2 da
     * verificador zero.
     */
    private static function digito(string $parcial): string
    {
        $soma = 0;

        foreach (array_reverse(str_split($parcial)) as $posicao => $caractere) {
            $soma += (ord($caractere) - 48) * self::PESOS[$posicao % 8];
        }

        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }
}
