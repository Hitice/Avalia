<?php

namespace App\Actions\Financeiro;

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Fecha uma vez a competência anterior para cada contrato ativo. */
class FecharCompetenciasVencidas
{
    public function __construct(private readonly FecharCompetencia $fechar) {}

    public function __invoke(?\DateTimeInterface $agora = null): int
    {
        $competencia = CarbonImmutable::instance($agora ?? now())->startOfMonth()->subMonth()->format('Y-m');
        $clientes = Cliente::query()
            ->whereNotNull('plano_id')
            ->where('situacao', '!=', 'inativo')
            ->whereDoesntHave('faturas', fn ($q) => $q->where('competencia', $competencia))
            ->get();

        $emitidas = 0;

        foreach ($clientes as $cliente) {
            // Cada contrato e isolado: a rotina roda de madrugada sobre a base
            // inteira, e um contrato problematico nao pode deixar sem cobranca
            // todos os que viriam depois dele na fila.
            try {
                $resultado = ($this->fechar)($cliente, $competencia);

                if ($resultado['fatura']) {
                    $emitidas++;

                    continue;
                }

                Log::channel('auditoria')->warning('fechamento.recusado', [
                    'cliente_id' => $cliente->id,
                    'competencia' => $competencia,
                    'motivo' => $resultado['erro'],
                ]);
            } catch (Throwable $e) {
                Log::channel('auditoria')->error('fechamento.falhou', [
                    'cliente_id' => $cliente->id,
                    'competencia' => $competencia,
                    'excecao' => $e->getMessage(),
                ]);
            }
        }

        // Conta o que saiu, e nao quem era candidato: este numero vai para o
        // log de operacao, e contar candidato faria a rotina reportar sucesso
        // num mes em que nada foi cobrado.
        return $emitidas;
    }
}
