<?php

namespace App\Actions\Financeiro;

use App\Models\Cliente;
use App\Models\Fatura;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Ponto único de confirmação de pagamento de uma fatura.
 *
 * Liquidar libera a comissão do vendedor, e é aqui que essa regra vive: cliente
 * que não pagou não gera comissão (PDD.md, seção 9).
 *
 * É idempotente. A mesma confirmação pode chegar mais de uma vez, por repetição
 * de clique ou por reentrega de uma origem automática, e o segundo pedido não
 * pode liberar comissão em duplicidade nem reescrever o histórico financeiro.
 * Por isso a fatura é relida sob trava e a liquidação já registrada devolve o
 * estado atual em vez de gravar de novo.
 */
class RegistrarLiquidacao
{
    /** Confirmacao vinda de gente, digitada na tela. */
    public const ORIGEM_MANUAL = 'manual';

    /** Confirmacao vinda do provedor de cobranca. */
    public const ORIGEM_AUTOMATICA = 'automatica';

    /**
     * @param  string  $origem  quem confirmou, que e o que diferencia uma baixa
     *                          conferida no extrato de uma digitada a mao
     */
    public function __invoke(
        Fatura $fatura,
        ?\DateTimeInterface $liquidadaEm = null,
        string $origem = self::ORIGEM_AUTOMATICA,
    ): Fatura {
        return DB::transaction(function () use ($fatura, $liquidadaEm, $origem) {
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

            // A origem entra na trilha porque separa a baixa conferida no
            // extrato da digitada a mao. Baixa manual libera comissao sem que
            // dinheiro nenhum tenha entrado, e quem audita precisa distinguir.
            Auditar::registrar('fatura.liquidada', $fatura, [
                'competencia' => $fatura->competencia,
                'total_cents' => $fatura->total_cents,
                'comissao_liberada_cents' => $fatura->comissao_cents,
                'origem' => $origem,
            ]);

            return $fatura->fresh();
        });
    }
}
