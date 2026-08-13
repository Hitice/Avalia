<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * As estrelas dos cards de consulta, pelo quartil do preco.
 *
 * No catalogo o preco acompanha a profundidade da pesquisa (mais bases, mais
 * dado, mais caro), entao o quartil e um resumo honesto de "quanto esta
 * consulta traz", sem nota editorial para manter. Zero e o quartil de entrada,
 * nao um defeito, e por isso a tela mantem as estrelas apagadas visiveis.
 *
 * Vive aqui porque duas telas mostram os mesmos cards (a carteira do vendedor
 * e o portal da empresa) e a regua so funciona se as duas contarem igual.
 */
final class Estrelas
{
    /**
     * @param  Collection<int|string, int>  $precosCents  servico_id => preco
     * @return Collection<int|string, int> servico_id => 0 a 3
     */
    public static function porPreco(Collection $precosCents): Collection
    {
        $ordenados = $precosCents->sort()->values();

        return $precosCents->map(function (int $preco) use ($ordenados) {
            $abaixo = $ordenados->search(fn (int $v) => $v > $preco);
            $fracao = ($abaixo === false ? $ordenados->count() : $abaixo) / max(1, $ordenados->count());

            return (int) min(3, floor($fracao * 4 - 0.0001));
        });
    }
}
