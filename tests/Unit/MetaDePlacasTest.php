<?php

use App\Support\MetaDePlacas;
use Illuminate\Support\Carbon;

it('divide o que falta pelos dias que restam, e para no fim do mes', function () {
    $mes = Carbon::parse('2026-10-01');

    $m = MetaDePlacas::doMes($mes, 5, 12, 5, Carbon::parse('2026-10-21 10:00'));
    expect($m['meta'])->toBe(25)->and($m['faltam'])->toBe(13)->and($m['diasRestantes'])->toBe(10)->and($m['porDia'])->toBe(2)->and($m['hoje'])->toBe(21);

    expect(MetaDePlacas::doMes($mes, 5, 30, 5, Carbon::parse('2026-10-21'))['porDia'])->toBe(0)
        ->and(MetaDePlacas::doMes($mes, 5, 0, 5, Carbon::parse('2026-11-03'))['diasRestantes'])->toBe(0)
        ->and(MetaDePlacas::doMes($mes, 5, 0, 5, Carbon::parse('2026-09-03'))['diasRestantes'])->toBe(31);
});
