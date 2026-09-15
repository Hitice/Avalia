<?php

namespace App\Support;

/**
 * A casa que assina os documentos.
 *
 * Le o cadastro de config/empresa.php e entrega pronto o que vai na tela e no
 * papel. A formatacao mora aqui, e nao no ponto de uso, para que a linha de
 * identificacao saia igual no rodape do site, na fatura e no laudo: eram tres
 * strings soltas, e duas delas ficaram com o CNPJ antigo.
 */
final class Empresa
{
    public static function razaoSocial(): string
    {
        return (string) config('empresa.razao_social', '');
    }

    public static function cnpj(): string
    {
        return (string) config('empresa.cnpj', '');
    }

    public static function site(): string
    {
        return (string) config('empresa.site', '');
    }

    public static function email(): string
    {
        return (string) config('empresa.email', '');
    }

    /** Endereco da sede numa linha, do jeito que se escreve em documento. */
    public static function endereco(): string
    {
        $e = (array) config('empresa.endereco', []);

        $logradouro = trim(implode(', ', array_filter([
            $e['logradouro'] ?? null,
            $e['numero'] ?? null,
            $e['complemento'] ?? null,
        ])));

        return trim(implode(' · ', array_filter([
            $logradouro !== '' ? $logradouro : null,
            $e['bairro'] ?? null,
            trim(implode('/', array_filter([$e['cidade'] ?? null, $e['uf'] ?? null]))) ?: null,
            isset($e['cep']) ? 'CEP '.$e['cep'] : null,
        ])));
    }

    /**
     * A linha que identifica o emissor no pe de um documento.
     *
     * Site e CNPJ, nessa ordem: quem recebe o PDF procura primeiro onde falar
     * com a Avalia, e so depois confere quem emitiu. Cabe numa linha so, que e
     * o que o fecho do PDF reserva.
     */
    public static function assinatura(): string
    {
        return self::site().' · CNPJ '.self::cnpj();
    }
}
