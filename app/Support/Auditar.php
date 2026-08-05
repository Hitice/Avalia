<?php

namespace App\Support;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** Centraliza o formato do rastro de auditoria e evita registros incompletos. */
final class Auditar
{
    /** @param array<string, mixed> $dados */
    public static function registrar(string $acao, ?Model $entidade = null, array $dados = []): Auditoria
    {
        $request = app()->runningInConsole() ? null : request();

        $registro = Auditoria::create([
            'staff_id' => auth('staff')->id(),
            'acao' => $acao,
            'entidade_tipo' => $entidade ? $entidade::class : null,
            'entidade_id' => $entidade?->getKey(),
            'dados' => $dados,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'ocorreu_em' => now(),
        ]);

        Log::channel('auditoria')->info($acao, [
            'entidade_tipo' => $registro->entidade_tipo,
            'entidade_id' => $registro->entidade_id,
        ]);

        return $registro;
    }
}
