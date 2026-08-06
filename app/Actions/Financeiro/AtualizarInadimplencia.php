<?php

namespace App\Actions\Financeiro;

use App\Mail\ConsultasBloqueadas;
use App\Mail\FaturaVencida;
use App\Models\Fatura;
use App\Support\Auditar;
use Illuminate\Support\Facades\Mail;

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

                    // A transicao acontece uma vez por fatura, entao um aviso
                    // por vencimento. E o que da sentido a janela de tolerancia:
                    // o cliente sabe que venceu, que nada foi cortado e ate
                    // quando regularizar.
                    try {
                        if ($fatura->cliente?->email) {
                            Mail::to($fatura->cliente->email)->send(new FaturaVencida($fatura));
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                if ($fatura->vencimento()->addDays(Fatura::DIAS_ATE_BLOQUEIO) <= $agora) {
                    $cliente = $fatura->cliente;

                    if ($cliente && $cliente->situacao === 'ativo') {
                        $cliente->update(['situacao' => 'inadimplente']);
                        Auditar::registrar('cliente.inadimplente', $cliente, ['fatura_id' => $fatura->id]);

                        // So na transicao para inadimplente, entao um e-mail por
                        // bloqueio: amanha o cliente ja nao esta 'ativo' e nao
                        // entra aqui de novo. Falha de envio nao desfaz o bloqueio.
                        try {
                            if ($cliente->email) {
                                Mail::to($cliente->email)->send(new ConsultasBloqueadas($fatura));
                            }
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }
            });

        return $afetadas;
    }
}
