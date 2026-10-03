<?php

namespace App\Actions\Negocios;

use App\Actions\Links\EncurtarLink;
use App\Exceptions\Recusa;
use App\Models\Link;
use App\Models\Negocio;
use App\Support\Auditar;
use App\Support\LinkDeAvaliacao;
use Illuminate\Support\Facades\DB;

/**
 * Transforma um Place ID no link curto que o cliente imprime no balcao.
 *
 * O endereco do Google e longo e cheio de parametro, e ninguem digita: o que vai
 * no adesivo, no cardapio e na plaquinha e o curto. Encurtar aqui tambem da ao
 * cliente a contagem de quantos abriram o pedido, que e a unica medida que ele
 * tem de que o material esta funcionando.
 *
 * Usa o encurtador que a casa ja tem. Ele desduplica por destino, entao pedir o
 * link duas vezes para o mesmo estabelecimento devolve o mesmo codigo, com os
 * cliques juntos em vez de divididos.
 */
class GerarLinkDeAvaliacao
{
    public function __construct(private readonly EncurtarLink $encurtar) {}

    public function __invoke(string $placeId, string $nome, ?Negocio $negocio = null): Link
    {
        $placeId = trim($placeId);

        // Conferido antes de montar: Place ID errado vira um link que abre uma
        // pagina de erro do Google, e isso so se descobre depois de a grafica
        // imprimir.
        if (! LinkDeAvaliacao::pareceValido($placeId)) {
            throw new Recusa('Esse Place ID não tem a cara de um. Busque pelo nome em vez de digitá-lo.');
        }

        return DB::transaction(function () use ($placeId, $nome, $negocio) {
            $link = ($this->encurtar)(
                LinkDeAvaliacao::de($placeId),
                'Avaliação no Google: '.trim($nome),
            );

            // Sem negocio na base, o link cria um: senao o que a casa ativou
            // ficava so no encurtador, e a lista de negocios nao sabia dele.
            $negocio ??= Negocio::firstWhere('place_id', $placeId)
                ?? Negocio::create(['nome' => trim($nome), 'situacao' => \App\Enums\SituacaoNegocio::Recebido->value, 'origem' => 'link', 'vendedor_id' => auth('staff')->id()]);

            $negocio->update(['place_id' => $placeId, 'link_avaliacao_id' => $link->id]);

            Auditar::registrar('negocio.link-avaliacao', $negocio, [
                'place_id' => $placeId,
                'codigo' => $link->codigo,
            ]);

            return $link;
        });
    }
}
