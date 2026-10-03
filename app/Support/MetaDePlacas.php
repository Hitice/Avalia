<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A meta e por dia: N placas por pessoa por dia util. O mes e a soma dos dias
 * uteis; a previsao desenha, nos dias uteis que faltam, a meta do dia.
 */
final class MetaDePlacas
{
    /** @return array{porDia: int, meta: int, vendidas: int, faltam: int, diasRestantes: int, hoje: int} */
    public static function doMes(Carbon $mes, int $pessoas, int $vendidas, int $porPessoa, ?Carbon $hoje = null): array
    {
        $hoje ??= Carbon::now();
        $porDia = $pessoas * $porPessoa;

        // Mes passado nao tem dia restante; mes futuro tem todos.
        $diaDeHoje = $hoje->isSameMonth($mes) ? $hoje->day : ($hoje->lessThan($mes) ? 0 : $mes->daysInMonth);

        $uteis = $uteisRestantes = 0;
        $cursor = $mes->copy()->startOfMonth();

        while ($cursor->month === $mes->month) {
            if (! $cursor->isWeekend()) {
                $uteis++;
                $uteisRestantes += $cursor->day > $diaDeHoje ? 1 : 0;
            }

            $cursor->addDay();
        }

        $meta = $porDia * $uteis;

        return [
            'porDia' => $porDia,
            'meta' => $meta,
            'vendidas' => $vendidas,
            'faltam' => max(0, $meta - $vendidas),
            'diasRestantes' => $uteisRestantes,
            'hoje' => $diaDeHoje,
        ];
    }
}
