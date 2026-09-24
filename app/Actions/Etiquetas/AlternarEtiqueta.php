<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Support\Auditar;

/**
 * Liga e desliga a plaquinha, num clique.
 *
 * Suspender nao apaga nada e nao solta o codigo: a placa continua no balcao do
 * cliente, e ela volta a funcionar exatamente como estava. E por isso que
 * suspensa e um estado, e nao uma baixa.
 *
 * Plaquinha em branco nao entra aqui. Suspender o que nunca apontou para lugar
 * nenhum trocaria a pagina de "ainda nao ativada", que explica o produto a
 * quem leu a placa, pela de "fora do ar", que assusta sem motivo.
 */
class AlternarEtiqueta
{
    public function __invoke(Etiqueta $etiqueta): Etiqueta
    {
        $nova = match ($etiqueta->situacao) {
            SituacaoEtiqueta::Ativa => SituacaoEtiqueta::Suspensa,
            SituacaoEtiqueta::Suspensa => SituacaoEtiqueta::Ativa,
            default => throw new Recusa('Só etiqueta ativa ou suspensa pode ser ligada e desligada.'),
        };

        $etiqueta->update(['situacao' => $nova]);

        Auditar::registrar('etiquetas.alternada', $etiqueta, ['situacao' => $nova->value]);

        return $etiqueta;
    }
}
