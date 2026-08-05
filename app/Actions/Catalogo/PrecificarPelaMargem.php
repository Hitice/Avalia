<?php

namespace App\Actions\Catalogo;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Margem;

/**
 * Reprecifica a tabela pela escada de margem.
 *
 * Cada faixa recebe o preco que entrega a margem dela: a maior fica no piso
 * comercial e as de baixo rendem mais. E isso que faz o pacote maior valer a
 * pena para o cliente sem tirar dinheiro da Avalia.
 */
class PrecificarPelaMargem
{
    /** @return array{recalculados: int, sem_custo: int} */
    public function __invoke(Catalogo $catalogo): array
    {
        $precos = $catalogo->precos()
            ->whereNotNull('custo_cents')
            ->get(['id', 'catalogo_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents']);

        $margemDaFaixa = $catalogo->margemPorFaixa(Catalogo::faixasDe($precos));

        $lote = $precos
            ->map(fn (Preco $preco) => [
                'id' => $preco->id,
                'catalogo_id' => $preco->catalogo_id,
                'servico_id' => $preco->servico_id,
                'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                'custo_cents' => $preco->custo_cents,
                'antes' => $preco->preco_cents,
                'preco_cents' => Margem::precoAlvoCents(
                    $preco->custo_cents,
                    $catalogo->imposto_bps,
                    $catalogo->comissaoBps(),
                    $margemDaFaixa[$preco->consumo_minimo_cents] ?? $catalogo->margem_alvo_bps,
                ),
            ])
            ->reject(fn (array $l) => $l['antes'] === $l['preco_cents'])
            ->map(fn (array $l) => collect($l)->except('antes')->all())
            ->values()
            ->all();

        if ($lote !== []) {
            Preco::upsert($lote, ['id'], ['preco_cents']);
        }

        return [
            'recalculados' => count($lote),
            'sem_custo' => $catalogo->precos()->whereNull('custo_cents')->count(),
        ];
    }
}
