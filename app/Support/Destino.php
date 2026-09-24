<?php

namespace App\Support;

/**
 * Para onde uma plaquinha aponta.
 *
 * O destino e o unico campo do sistema que manda um desconhecido para fora do
 * nosso dominio, sem clique intermediario e sem aviso. Quem le a placa confia
 * no lojista, o lojista confia na gente, e no fim da corrente esta um campo de
 * texto. Por isso ele e conferido aqui, e nao so pelo `url` do validador.
 */
final class Destino
{
    /** So o que um navegador abre sozinho, sem instalar nada e sem executar nada. */
    private const ESQUEMAS = ['http', 'https'];

    public const TAMANHO_MAXIMO = 1000;

    /**
     * O endereco como ele deve ser guardado.
     *
     * Completa o `https://` que falta. Quase ninguem digita o esquema, e sem
     * ele o navegador trata `padaria.com.br` como caminho relativo: a
     * plaquinha mandaria o fregues para uma pagina nossa que nao existe.
     */
    public static function normalizar(?string $entrada): string
    {
        $limpo = trim((string) $entrada);

        if ($limpo === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $limpo) === 1) {
            return $limpo;
        }

        return 'https://'.ltrim($limpo, '/');
    }

    /**
     * O motivo de o destino nao servir, ou null quando serve.
     *
     * Devolve o motivo em vez de um booleano porque quem digitou precisa saber
     * o que corrigir: "endereço inválido" faz a pessoa tentar a mesma coisa de
     * novo.
     */
    public static function problema(?string $entrada): ?string
    {
        $destino = self::normalizar($entrada);

        if ($destino === '') {
            return 'Informe o endereço de destino.';
        }

        if (mb_strlen($destino) > self::TAMANHO_MAXIMO) {
            return 'O endereço é longo demais para caber numa plaquinha.';
        }

        $partes = parse_url($destino);

        // `javascript:`, `data:` e afins. O navegador do fregues executaria o
        // que estivesse escrito ali, com a plaquinha de um cliente nosso como
        // porta de entrada.
        if (! in_array(strtolower($partes['scheme'] ?? ''), self::ESQUEMAS, true)) {
            return 'O destino precisa começar com http:// ou https://.';
        }

        if (blank($partes['host'] ?? null) || ! str_contains($partes['host'], '.')) {
            return 'O endereço não tem um domínio válido.';
        }

        // Plaquinha apontando para a leitura de plaquinha e laco: o navegador
        // roda ate desistir e o fregues nao chega a lugar nenhum.
        if (self::apontaParaOEncurtador($partes)) {
            return 'O destino não pode ser o endereço de outra plaquinha.';
        }

        return null;
    }

    public static function valido(?string $entrada): bool
    {
        return self::problema($entrada) === null;
    }

    /** @param  array<string, mixed>  $partes */
    private static function apontaParaOEncurtador(array $partes): bool
    {
        $nosso = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (blank($nosso) || strcasecmp((string) $partes['host'], (string) $nosso) !== 0) {
            return false;
        }

        return (bool) preg_match('~^/q/~i', (string) ($partes['path'] ?? ''));
    }
}
