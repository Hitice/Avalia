<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Models\Etiqueta;
use App\Support\Auditar;

/**
 * Tira a plaquinha de circulacao para sempre.
 *
 * Fim de linha: placa quebrada, cliente que saiu, tiragem que deu errado. Nao
 * e exclusao, pela regra da casa, e o codigo NAO volta para o bolo do sorteio.
 * Reciclado, ele mandaria a freguesia do cliente antigo, que ainda tem a placa
 * velha em algum lugar, para a loja de um estranho.
 */
class BaixarEtiqueta
{
    public function __invoke(Etiqueta $etiqueta, ?string $motivo = null): Etiqueta
    {
        $etiqueta->update(['situacao' => SituacaoEtiqueta::Baixada]);

        Auditar::registrar('etiquetas.baixada', $etiqueta, ['motivo' => $motivo]);

        return $etiqueta;
    }
}
