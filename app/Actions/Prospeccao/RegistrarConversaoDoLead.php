<?php

namespace App\Actions\Prospeccao;

use App\Enums\SituacaoLead;
use App\Models\Cliente;
use App\Models\Lead;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Fecha o ciclo: o lead aponta para a empresa que ele virou.
 *
 * Roda DEPOIS de o cadastro do cliente estar gravado, e nunca antes: lead
 * marcado como convertido sem empresa do outro lado e a pior das duas metades,
 * porque ele sai da fila de quem prospecta e ninguem descobre que a venda nao
 * existe.
 *
 * O lead nao e apagado. "De onde veio este cliente" e a pergunta que a
 * prospeccao existe para responder, e a resposta e este vinculo.
 *
 * Sem efeito sobre lead ja convertido: reabrir o formulario e salvar de novo nao
 * pode reescrever a data da primeira conversao nem trocar a empresa de origem.
 */
class RegistrarConversaoDoLead
{
    public function __invoke(Lead $lead, Cliente $cliente): bool
    {
        if ($lead->jaEhCliente()) {
            return false;
        }

        return DB::transaction(function () use ($lead, $cliente) {
            $lead->forceFill([
                'situacao' => SituacaoLead::Convertido,
                'cliente_id' => $cliente->id,
                'convertido_em' => now(),
                // Agendamento cumprido nao fica pendurado na fila de atrasados.
                'agendado_para' => null,
            ])->save();

            Auditar::registrar('lead.convertido', $lead, [
                'cliente_id' => $cliente->id,
                'razao_social' => $cliente->razao_social,
            ]);

            return true;
        });
    }
}
