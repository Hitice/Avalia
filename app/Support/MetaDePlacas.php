<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** A meta do mes e o ritmo que falta: calculo puro, para o grafico e os cartoes. */
final class MetaDePlacas
{
    /** @return array{meta: int, vendidas: int, faltam: int, diasRestantes: int, porDia: int, hoje: int} */
    public static function doMes(Carbon $mes, int $pessoas, int $vendidas, int $porPessoa, ?Carbon $hoje = null): array
    {
        $hoje ??= Carbon::now();
        $meta = $pessoas * $porPessoa;
        $faltam = max(0, $meta - $vendidas);

        // Mes passado nao tem dia restante; mes futuro tem todos.
        $diaDeHoje = $hoje->isSameMonth($mes) ? $hoje->day : ($hoje->lessThan($mes) ? 0 : $mes->daysInMonth);
        $diasRestantes = max(0, $mes->daysInMonth - $diaDeHoje);

        return [
            'meta' => $meta,
            'vendidas' => $vendidas,
            'faltam' => $faltam,
            'diasRestantes' => $diasRestantes,
            'porDia' => $diasRestantes > 0 ? (int) ceil($faltam / $diasRestantes) : 0,
            'hoje' => $diaDeHoje,
        ];
    }
}
