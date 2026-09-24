<?php

namespace App\Actions\Etiquetas;

use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\RenovacaoEtiqueta;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Compra mais um ano de servico.
 *
 * Conta a partir do vencimento antigo, e nao de hoje: quem paga com quinze
 * dias de atraso comprou um ano, e nao um ano menos quinze dias. Vencimento
 * ja distante tambem se empilha, pelo mesmo motivo.
 *
 * Grava o valor COBRADO, e nao o de tabela de hoje: a renovacao do ano que vem
 * pode custar outro preco, e o historico precisa continuar explicando o que
 * foi cobrado de quem.
 */
class RenovarEtiqueta
{
    public function __invoke(Etiqueta $etiqueta, ?int $valorCents = null): Etiqueta
    {
        if ($etiqueta->vendida_em === null) {
            throw new Recusa('Plaquinha que nunca foi vendida não tem o que renovar.');
        }

        $meses = (int) config('etiquetas.validade_meses');
        $valor = $valorCents ?? (int) config('etiquetas.precos.renovacao_cents');

        return DB::transaction(function () use ($etiqueta, $meses, $valor) {
            $de = $etiqueta->vence_em ?? now();
            $ate = $de->copy()->addMonths($meses);

            $etiqueta->update(['vence_em' => $ate, 'avisada_em' => null]);

            RenovacaoEtiqueta::create([
                'etiqueta_id' => $etiqueta->id,
                'valor_cents' => $valor,
                'de' => $de,
                'ate' => $ate,
                'staff_id' => auth('staff')->id(),
            ]);

            Auditar::registrar('etiquetas.renovada', $etiqueta, [
                'ate' => $ate->toDateString(),
                'valor_cents' => $valor,
            ]);

            return $etiqueta->refresh();
        });
    }
}
