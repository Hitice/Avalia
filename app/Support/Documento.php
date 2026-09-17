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

    /**
     * CPF: onze digitos com os dois verificadores conferindo.
     *
     * Conta diferente da do CNPJ de proposito. Aqui os pesos decrescem, de 10
     * a 2 no primeiro digito e de 11 a 2 no segundo, e nao existe posicao
     * alfanumerica: o CPF nunca recebeu essa mudanca.
     */
    public static function cpfValido(?string $entrada): bool
    {
        $cpf = preg_replace('/\D/', '', (string) $entrada) ?? '';

        // Mesma armadilha do CNPJ: 111.111.111-11 passa na conta e nao existe.
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }

            $resto = $soma % 11;

            if ((int) $cpf[$posicao] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aceita o produtor pessoa fisica ou juridica sem perguntar qual e.
     *
     * Quem se cadastra na Cobranca digita o documento que tem, e obrigar a
     * escolher "CPF ou CNPJ" antes de digitar e um campo a mais para errar: o
     * tamanho ja diz qual conta usar.
     */
    public static function documentoValido(?string $entrada): bool
    {
        $documento = self::normalizarCnpj($entrada);

        return strlen($documento) === 11
            ? self::cpfValido($documento)
            : self::cnpjValido($documento);
    }

    /** 12345678909 -> "123.456.789-09" */
    public static function formatarCpf(?string $entrada): string
    {
        $cpf = preg_replace('/\D/', '', (string) $entrada) ?? '';

        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return vsprintf('%s.%s.%s-%s', [
            substr($cpf, 0, 3),
            substr($cpf, 3, 3),
            substr($cpf, 6, 3),
            substr($cpf, 9, 2),
        ]);
    }

    /**
     * Impressao digital do documento, para procurar sem decifrar.
     *
     * Coluna cifrada nao se pesquisa nem se indexa: para achar "outro pedido
     * deste CPF" era preciso carregar todos os pedidos em aberto e decifrar um
     * por um, o que custa uma descriptografia por linha em cada checkout.
     *
     * O HMAC resolve os dois lados: e deterministico, entao indexa e compara;
     * e depende da chave da aplicacao, entao quem le o banco sem a chave nao
     * consegue testar CPFs ate achar o que casa. Hash puro, sem chave, cairia
     * nesse ataque em minutos, porque o espaco de CPFs validos e pequeno.
     *
     * Depende de APP_KEY como os campos cifrados: rotacionar a chave exige
     * reescrever os hashes junto.
     */
    public static function hash(?string $entrada): string
    {
        $documento = self::normalizarCnpj($entrada);

        if ($documento === '') {
            return '';
        }

        return hash_hmac('sha256', $documento, (string) config('app.key'));
    }

    /** O documento formatado pelo que ele e: onze digitos viram CPF, o resto CNPJ. */
    public static function formatar(?string $entrada): string
    {
        $documento = self::normalizarCnpj($entrada);

        return strlen($documento) === 11
            ? self::formatarCpf($documento)
            : self::formatarCnpj($documento);
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
