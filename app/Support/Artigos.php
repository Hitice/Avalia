<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * O catalogo do blog.
 *
 * Le a ficha dos artigos de config/blog.php e entrega pronta a lista da
 * vitrine e a ficha de um artigo so. A data em extenso e formatada aqui, e
 * nao na view: a listagem e a pagina do artigo mostram a mesma data, e
 * escrita nos dois lugares ela ja sairia com dois formatos.
 */
final class Artigos
{
    /** Todos os artigos, do mais recente para o mais antigo. */
    public static function todos(): array
    {
        $artigos = array_map(
            fn (string $slug) => self::ficha($slug),
            array_keys((array) config('blog', []))
        );

        usort($artigos, fn (array $a, array $b) => strcmp($b['data'], $a['data']));

        return $artigos;
    }

    /** Existe artigo com este endereco? */
    public static function existe(string $slug): bool
    {
        return config('blog.'.$slug) !== null;
    }

    /**
     * A ficha de um artigo, com o slug e a data em extenso ja dentro.
     *
     * O slug entra no retorno porque quem monta o link precisa dele, e
     * carregar a chave do array por fora ate a view era o que fazia o cartao
     * apontar para o artigo errado quando a lista era reordenada.
     */
    public static function ficha(string $slug): array
    {
        $artigo = (array) config('blog.'.$slug, []);

        return $artigo + [
            'slug' => $slug,
            'data_extenso' => isset($artigo['data'])
                ? Carbon::parse($artigo['data'])->translatedFormat('j \d\e F \d\e Y')
                : '',
        ];
    }
}
