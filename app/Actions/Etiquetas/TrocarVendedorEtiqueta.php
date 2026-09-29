<?php

namespace App\Actions\Etiquetas;

use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\Staff;
use App\Support\Auditar;

/**
 * Corrige a quem a venda da plaquinha pertence.
 *
 * Existe porque o sistema descobre o vendedor pelo que aconteceu, e nao pelo que
 * foi combinado: quem vende passa a placa para outra pessoa cadastrar, a
 * administracao aponta o codigo para ajudar, e nesses casos o credito sai errado
 * sem ninguem errar nada. Um repasse pago pelo registro errado se descobre no
 * fim do mes, quando alguem reclama do valor.
 *
 * So a administracao troca. Vendedor que pudesse reatribuir venda escolheria a
 * propria comissao, e a conferencia do mes deixaria de ter valor.
 *
 * Nulo e permitido de proposito: venda feita por quem ja saiu da equipe existe,
 * e "nao identificado" e mais honesto que creditar a quem estiver por perto. A
 * venda continua contando no faturamento; o que falta e o nome.
 */
class TrocarVendedorEtiqueta
{
    public function __invoke(Etiqueta $etiqueta, ?int $vendedorId): Etiqueta
    {
        if ($etiqueta->vendida_em === null) {
            throw new Recusa('Esta plaquinha não tem venda registrada, então não há vendedor a definir. Aponte e venda primeiro.');
        }

        $novo = $this->conferir($vendedorId);

        $anterior = $etiqueta->vendedor_id === null ? null : (int) $etiqueta->vendedor_id;

        if ($anterior === $novo?->id) {
            return $etiqueta;
        }

        $etiqueta->update(['vendedor_id' => $novo?->id]);

        // O nome entra na trilha, e nao so o id: a conta pode ser removida
        // depois, e "vendedor_id 7" nao explica nada a quem le a auditoria um
        // ano adiante. Ver App\Support\Auditar::rotuloDe, mesma razao.
        Auditar::registrar('etiquetas.vendedor.trocado', $etiqueta, [
            'de' => $anterior,
            'para' => $novo?->id,
            'para_nome' => $novo?->nome ?? 'Não identificado',
        ]);

        return $etiqueta->refresh();
    }

    /**
     * A conta que pode receber a venda.
     *
     * Aceita conta desativada, porque venda antiga pertence a quem vendeu mesmo
     * que a pessoa ja tenha saido; recusar deixaria a correcao impossivel
     * justamente no caso em que ela mais aparece.
     */
    private function conferir(?int $vendedorId): ?Staff
    {
        if ($vendedorId === null) {
            return null;
        }

        $staff = Staff::withTrashed()->find($vendedorId);

        if ($staff === null) {
            throw new Recusa('Não achei essa conta na equipe.');
        }

        return $staff;
    }
}
