<?php

namespace App\Support;

/**
 * O endereco que abre direto o formulario de avaliacao do Google.
 *
 * Calculo puro: dado o Place ID, o endereco e sempre o mesmo. Fica em um lugar
 * so porque e o que o cliente imprime no balcao, e um formato errado ai e uma
 * placa inutil que ninguem confere antes de mandar para a grafica.
 *
 * `search.google.com/local/writereview` e a forma documentada que aceita Place
 * ID e funciona no celular e no computador. A forma curta `g.page/r/<id>/review`
 * nao serve aqui: ela usa o CID, que e outro identificador, e so aparece no
 * painel do proprio dono do perfil.
 */
final class LinkDeAvaliacao
{
    public static function de(string $placeId): string
    {
        return 'https://search.google.com/local/writereview?placeid='.rawurlencode(trim($placeId));
    }

    /**
     * O Place ID parece com um Place ID.
     *
     * Confere forma, e nao existencia: o Google e quem sabe se ele existe, e a
     * conferencia aqui evita gastar uma requisicao cobrada com texto digitado
     * errado. Os que a Places API devolve comecam com `ChI` na pratica, mas o
     * proprio Google documenta que o formato e opaco e pode mudar, entao a
     * regra se limita ao alfabeto e ao tamanho.
     */
    public static function pareceValido(?string $placeId): bool
    {
        $limpo = trim((string) $placeId);

        return strlen($limpo) >= 10
            && strlen($limpo) <= 255
            && preg_match('/^[A-Za-z0-9_-]+$/', $limpo) === 1;
    }
}
