<?php

namespace App\Actions\Financeiro;

use App\Models\Cliente;
use App\Models\Fatura;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz a liquidacao de uma fatura e recolhe a comissao liberada.
 *
 * Existe porque pagamento desfeito acontece: chargeback, Pix devolvido, boleto
 * baixado por engano. Ate aqui a liquidacao era um caminho so de ida, e o
 * dinheiro do vendedor ja tinha saido do controle sem volta.
 *
 * O que este estorno NAO faz, de proposito: nao apaga a liquidacao anterior nem
 * mexe no valor da fatura. A competencia continua fechada, com o mesmo total, o
 * mesmo imposto e a mesma comissao apurada. O que muda e a situacao do
 * pagamento e a liberacao da comissao, porque o que foi desfeito foi o
 * recebimento, e nao a venda.
 *
 * A empresa volta a inadimplente se a fatura ja estava vencida. Nao volta a
 * bloqueada por decisao administrativa: essa e outra conversa, e desfazer um
 * pagamento nao autoriza reabrir uma punicao.
 */
class EstornarLiquidacao
{
    /** @return array{erro: string|null, fatura: Fatura|null} */
    public function __invoke(Fatura $fatura, string $motivo, ?\DateTimeInterface $agora = null): array
    {
        $momento = $agora ?? now();

        // A conferencia acontece DENTRO da transacao, sobre a fatura relida sob
        // trava. Ler o model que chegou por parametro decidiria com o estado de
        // quando ele foi carregado, que pode ser de antes da liquidacao.
        $resultado = DB::transaction(function () use ($fatura, $motivo, $momento) {
            $fatura = Fatura::lockForUpdate()->findOrFail($fatura->id);

            if (! $fatura->estaLiquidada()) {
                return ['erro' => 'Esta fatura não está liquidada.', 'fatura' => null];
            }

            // Vencida antes do estorno volta vencida, e nao pendente: o prazo
            // passou de verdade, e fingir o contrario adiaria o bloqueio.
            $venceu = $fatura->vencimento() < $momento;

            $fatura->update([
                'situacao_pagamento' => $venceu ? Fatura::PAGAMENTO_VENCIDO : Fatura::PAGAMENTO_PENDENTE,
                'liquidada_em' => null,
                'comissao_liberada_em' => null,
                'estornada_em' => $momento,
            ]);

            // A empresa perde de volta o acesso se a fatura ja passou do prazo.
            // Bloqueio administrativo e contrato encerrado ficam como estao.
            $cliente = Cliente::lockForUpdate()->find($fatura->cliente_id);

            if ($venceu && $cliente && $cliente->situacao === 'ativo') {
                $cliente->update(['situacao' => 'inadimplente']);
            }

            Auditar::registrar('fatura.estornada', $fatura, [
                'competencia' => $fatura->competencia,
                'total_cents' => $fatura->total_cents,
                'comissao_recolhida_cents' => $fatura->comissao_cents,
                'motivo' => $motivo,
            ]);

            return ['erro' => null, 'fatura' => $fatura->fresh()];
        });

        return $resultado;
    }
}
