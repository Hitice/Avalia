<?php

use App\Support\MetaDePlacas;
use Illuminate\Support\Carbon;

it('conta a meta por dia util, e os dias uteis que restam', function () {
    // Outubro de 2026 tem 22 dias uteis; depois do dia 21 (quarta) restam 7.
    $mes = Carbon::parse('2026-10-01');
    $m = MetaDePlacas::doMes($mes, 5, 12, 5, Carbon::parse('2026-10-21 10:00'));

    expect($m['porDia'])->toBe(25)->and($m['meta'])->toBe(25 * 22)->and($m['diasRestantes'])->toBe(7)->and($m['faltam'])->toBe(25 * 22 - 12);

    expect(MetaDePlacas::doMes($mes, 5, 0, 5, Carbon::parse('2026-11-03'))['diasRestantes'])->toBe(0)
        ->and(MetaDePlacas::doMes($mes, 5, 0, 5, Carbon::parse('2026-09-03'))['diasRestantes'])->toBe(22);
});
