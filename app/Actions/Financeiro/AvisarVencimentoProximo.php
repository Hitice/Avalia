<?php

namespace App\Actions\Financeiro;

use App\Mail\VencimentoProximo;
use App\Models\Fatura;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Manda o lembrete de vencimento das faturas que vencem nos proximos dias.
 *
 * A janela e "ate N dias", e nao "exatamente N dias": se a rotina ficar um dia
 * sem rodar, o lembrete sai atrasado em vez de nao sair. O carimbo
 * aviso_vencimento_em garante um lembrete so por fatura, rode a rotina quantas
 * vezes for.
 */
class AvisarVencimentoProximo
{
    public const DIAS_DE_ANTECEDENCIA = 3;

    public function __invoke(?\DateTimeInterface $agora = null): int
    {
        $hoje = CarbonImmutable::instance($agora ?? now())->startOfDay();
        $limite = $hoje->addDays(self::DIAS_DE_ANTECEDENCIA)->endOfDay();
        $enviados = 0;

        Fatura::query()
            ->where('situacao_pagamento', Fatura::PAGAMENTO_PENDENTE)
            ->whereNull('aviso_vencimento_em')
            ->with('cliente')
            ->get()
            ->filter(fn (Fatura $fatura) => $fatura->vencimento() >= $hoje && $fatura->vencimento() <= $limite)
            ->each(function (Fatura $fatura) use (&$enviados) {
                $email = $fatura->cliente?->email;

                if (! $email) {
                    return;
                }

                try {
                    Mail::to($email)->send(new VencimentoProximo($fatura));
                    $fatura->update(['aviso_vencimento_em' => now()]);
                    $enviados++;
                } catch (\Throwable $e) {
                    // Sem carimbo: a proxima rodada tenta de novo.
                    report($e);
                }
            });

        return $enviados;
    }
}
