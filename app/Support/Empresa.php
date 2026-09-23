<?php

namespace App\Support;

/**
 * A casa que assina os documentos.
 *
 * Le o cadastro de config/empresa.php e entrega pronto o que vai na tela e no
 * papel. A formatacao mora aqui, e nao no ponto de uso, para que a linha de
 * identificacao saia igual no rodape do site, na fatura e no laudo: eram tres
 * strings soltas, e duas delas ficaram com o CNPJ antigo.
 *
 * A marca tambem sai daqui. A casa tem um nome e cada produto tem o seu, e os
 * tres mudaram juntos uma vez: escritos a mao nas telas, sobrou "Avalia 360"
 * em pagina que ja falava de outro produto.
 */
final class Empresa
{
    public static function razaoSocial(): string
    {
        return (string) config('empresa.razao_social', '');
    }

    /** A marca da casa, a software house. */
    public static function marca(): string
    {
        return (string) config('empresa.marca', '');
    }

    /** O produto de pesquisa de score. */
    public static function marcaCredito(): string
    {
        return (string) config('empresa.marca_credito', '');
    }

    /** O produto de venda parcelada e cobranca. */
    public static function marcaCobranca(): string
    {
        return (string) config('empresa.marca_cobranca', '');
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
        return self::enderecoDe((array) config('empresa.endereco', []));
    }

    /**
     * So a praca: cidade, estado e CEP.
     *
     * E o que o rodape precisa. Rua e numero no pe de toda pagina ocupam
     * espaco para dizer o que ninguem foi ali procurar; quem precisa do
     * endereco completo esta lendo um contrato ou uma fatura, e ali ele
     * aparece inteiro.
     */
    public static function localidade(): string
    {
        return self::localidadeDe((array) config('empresa.endereco', []));
    }

    /** O rotulo do braco, que diz o que se faz ali. */
    public static function bracoRotulo(): string
    {
        return (string) config('empresa.braco.rotulo', '');
    }

    /**
     * O endereco do braco, numa linha.
     *
     * Nunca acompanhado de CNPJ: o braco nao tem inscricao propria, e por a
     * do cadastro ao lado dele seria dizer que existe uma filial registrada
     * naquele endereco.
     */
    public static function bracoEndereco(): string
    {
        return self::enderecoDe((array) config('empresa.braco', []));
    }

    public static function bracoLocalidade(): string
    {
        return self::localidadeDe((array) config('empresa.braco', []));
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

    private static function enderecoDe(array $e): string
    {
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

    private static function localidadeDe(array $e): string
    {
        return trim(implode(' · ', array_filter([
            trim(implode('/', array_filter([$e['cidade'] ?? null, $e['uf'] ?? null]))) ?: null,
            isset($e['cep']) ? 'CEP '.$e['cep'] : null,
        ])));
    }
}
