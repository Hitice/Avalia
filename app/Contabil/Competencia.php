<?php

namespace App\Contabil;

use App\Models\LancamentoFinanceiro;
use Illuminate\Support\Carbon;

/**
 * O mes de referencia, lido da URL e listado a partir do razao.
 *
 * Duas telas escolhem competencia, e a lista tem de ser a mesma.
 */
final class Competencia
{
    /** A pedida na URL, ou o mes corrente quando ela nao serve. */
    public static function pedida(?string $entrada): string
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $entrada) ? (string) $entrada : now()->format('Y-m');
    }

    /**
     * Do mes corrente para tras, ate o primeiro lancamento.
     *
     * @return list<string>
     */
    public static function existentes(): array
    {
        $primeira = LancamentoFinanceiro::min('competencia') ?: now()->format('Y-m');

        $cursor = Carbon::createFromFormat('Y-m', $primeira)->startOfMonth();
        $fim = now()->startOfMonth();
        $meses = [];

        while ($cursor->lessThanOrEqualTo($fim)) {
            array_unshift($meses, $cursor->format('Y-m'));
            $cursor->addMonth();
        }

        return $meses;
    }
}
