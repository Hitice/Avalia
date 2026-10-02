<?php

namespace App\Actions\Etiquetas;

use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Entrega um punhado de placas em branco a um vendedor.
 *
 * "Maria pegou 20 placas." A partir daqui elas sao dela e saem do bolo comum, e
 * cada uma que ela vender baixa do estoque dela sozinha, porque estoque e a
 * consulta `noEstoqueDe` e nao um contador que alguem precisa decrementar.
 *
 * Pega as de MENOR sequencia primeiro: a bancada trabalha em ordem, e entregar
 * numero salteado faz o vendedor conferir o lote carta a carta.
 */
class ConsignarPlacas
{
    private const TETO = 500;

    /** @return int quantas foram entregues */
    public function __invoke(Staff $vendedor, int $quantas): int
    {
        if ($quantas < 1 || $quantas > self::TETO) {
            throw new Recusa('Informe de 1 a '.self::TETO.' placas.');
        }

        return DB::transaction(function () use ($vendedor, $quantas) {
            // `lockForUpdate` porque dois admins entregando ao mesmo tempo
            // pegariam as mesmas placas e uma das entregas sumiria.
            $placas = Etiqueta::semDono()
                ->orderBy('sequencia')
                ->orderBy('id')
                ->limit($quantas)
                ->lockForUpdate()
                ->get();

            if ($placas->isEmpty()) {
                throw new Recusa('Não há placa livre no estoque da casa. Gere uma campanha nova antes de entregar.');
            }

            Etiqueta::whereIn('id', $placas->pluck('id'))->update([
                'consignada_para_id' => $vendedor->id,
                'consignada_em' => now(),
            ]);

            Auditar::registrar('etiquetas.consignadas', $vendedor, [
                'quantas' => $placas->count(),
                'de' => $placas->first()->codigo,
                'ate' => $placas->last()->codigo,
            ]);

            return $placas->count();
        });
    }

    /** Devolve ao bolo comum o que o vendedor nao vendeu. */
    public function devolver(Staff $vendedor): int
    {
        $quantas = Etiqueta::noEstoqueDe($vendedor->id)->count();

        if ($quantas === 0) {
            throw new Recusa($vendedor->nome.' não tem placa em estoque para devolver.');
        }

        Etiqueta::noEstoqueDe($vendedor->id)->update([
            'consignada_para_id' => null,
            'consignada_em' => null,
        ]);

        Auditar::registrar('etiquetas.devolvidas', $vendedor, ['quantas' => $quantas]);

        return $quantas;
    }
}
