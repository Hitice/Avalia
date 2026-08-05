<?php

namespace App\Actions\Financeiro;

use App\Models\Cliente;
use App\Models\Fatura;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Ponto único para o webhook do Asaas confirmar pagamento.
 *
 * É idempotente: o mesmo evento pode ser entregue mais de uma vez sem liberar
 * comissão em duplicidade nem alterar o histórico financeiro.
 */
class RegistrarLiquidacao
{
    public function __invoke(Fatura $fatura, ?\DateTimeInterface $liquidadaEm = null): Fatura
    {
        return DB::transaction(function () use ($fatura, $liquidadaEm) {
            $fatura = Fatura::lockForUpdate()->findOrFail($fatura->id);

            if ($fatura->estaLiquidada()) {
                return $fatura;
            }

            $momento = $liquidadaEm ?? now();
            $fatura->update([
                'situacao_pagamento' => Fatura::PAGAMENTO_LIQUIDADO,
                'liquidada_em' => $momento,
                'comissao_liberada_em' => $fatura->vendedor_id && $fatura->comissao_cents > 0 ? $momento : null,
            ]);

            $cliente = Cliente::lockForUpdate()->findOrFail($fatura->cliente_id);
            $haAberta = Fatura::query()
                ->where('cliente_id', $cliente->id)
                ->whereIn('situacao_pagamento', [Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO])
                ->exists();

            // Não revoga bloqueio administrativo ou contrato encerrado.
            if ($cliente->situacao === 'inadimplente' && ! $haAberta) {
                $cliente->update(['situacao' => 'ativo']);
            }

            Auditar::registrar('fatura.liquidada', $fatura, [
                'competencia' => $fatura->competencia,
                'total_cents' => $fatura->total_cents,
                'comissao_liberada_cents' => $fatura->comissao_cents,
            ]);

            return $fatura->fresh();
        });
    }
}
