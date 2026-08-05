<?php

namespace App\Actions\Financeiro;

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Cliente;
use Carbon\CarbonImmutable;

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

        foreach ($clientes as $cliente) {
            ($this->fechar)($cliente, $competencia);
        }

        return $clientes->count();
    }
}
