<?php

namespace App\Actions\Catalogo;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Dinheiro;

/**
 * Grava o custo do fornecedor, um por servico.
 *
 * O fornecedor cobra por consulta e nao pelo pacote do cliente, entao o valor
 * vale para todas as faixas do servico. Campo vazio devolve a linha ao estado
 * "custo nao cadastrado": zero seria mentira, significaria fornecedor de graca.
 */
class GravarCustos
{
    /** @param array<int|string, string|null> $valores id do servico => valor digitado */
    public function __invoke(Catalogo $catalogo, array $valores): int
    {
        $informados = collect($valores)
            ->map(fn ($valor) => Dinheiro::paraCentavos($valor))
            ->reject(fn (?int $centavos) => $centavos !== null && $centavos < 0);

        $lote = $catalogo->precos()
            ->whereIn('servico_id', $informados->keys())
            ->get(['id', 'catalogo_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents'])
            ->filter(fn (Preco $preco) => $preco->custo_cents !== $informados[$preco->servico_id])
            ->map(fn (Preco $preco) => [
                'id' => $preco->id,
                'catalogo_id' => $preco->catalogo_id,
                'servico_id' => $preco->servico_id,
                'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                'preco_cents' => $preco->preco_cents,
                'custo_cents' => $informados[$preco->servico_id],
            ])
            ->values()
            ->all();

        if ($lote !== []) {
            Preco::upsert($lote, ['id'], ['custo_cents']);
        }

        return collect($lote)->pluck('servico_id')->unique()->count();
    }
}
