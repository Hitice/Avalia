<?php

namespace App\Actions\Financeiro;

use App\Models\Fatura;
use App\Support\Auditar;

/** Atualiza atraso e bloqueio; deve ser chamado diariamente pelo agendador. */
class AtualizarInadimplencia
{
    public function __invoke(?\DateTimeInterface $agora = null): int
    {
        $agora = $agora ?? now();
        $afetadas = 0;

        Fatura::query()
            ->whereIn('situacao_pagamento', [Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO])
            ->get()
            ->filter(fn (Fatura $fatura) => $fatura->vencimento() < $agora)
            ->each(function (Fatura $fatura) use (&$afetadas, $agora) {
                if ($fatura->situacao_pagamento === Fatura::PAGAMENTO_PENDENTE) {
                    $fatura->update(['situacao_pagamento' => Fatura::PAGAMENTO_VENCIDO]);
                    $afetadas++;
                }

                if ($fatura->vencimento()->addDays(Fatura::DIAS_ATE_BLOQUEIO) <= $agora) {
                    $cliente = $fatura->cliente;

                    if ($cliente && $cliente->situacao === 'ativo') {
                        $cliente->update(['situacao' => 'inadimplente']);
                        Auditar::registrar('cliente.inadimplente', $cliente, ['fatura_id' => $fatura->id]);
                    }
                }
            });

        return $afetadas;
    }
}
