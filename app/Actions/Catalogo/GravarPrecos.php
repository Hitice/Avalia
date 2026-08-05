<?php

namespace App\Actions\Catalogo;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Dinheiro;
use App\Support\Margem;

/**
 * Grava precos de venda editados na matriz.
 *
 * Recusa o lote inteiro se qualquer preco furar o piso. Gravar so os validos
 * deixaria o operador achando que salvou tudo.
 */
class GravarPrecos
{
    /**
     * @param  array<int|string, string|null>  $valores  id do preco => valor digitado
     * @return array{gravados: int, furam: int, piso: int|null}
     */
    public function __invoke(Catalogo $catalogo, array $valores): array
    {
        $informados = collect($valores)
            ->map(fn ($valor) => Dinheiro::paraCentavos($valor))
            ->reject(fn (?int $centavos) => $centavos === null || $centavos < 0);

        $linhas = $catalogo->precos()
            ->whereIn('id', $informados->keys())
            ->get(['id', 'catalogo_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents'])
            ->filter(fn (Preco $preco) => $preco->preco_cents !== $informados[$preco->id]);

        $furam = $linhas->filter(fn (Preco $preco) => Margem::daPrejuizo(
            $informados[$preco->id], $preco->custo_cents, $catalogo->imposto_bps, $catalogo->comissaoBps(),
        ));

        if ($furam->isNotEmpty()) {
            return [
                'gravados' => 0,
                'furam' => $furam->count(),
                'piso' => Margem::pisoCents($furam->first()->custo_cents, $catalogo->imposto_bps, $catalogo->comissaoBps()),
            ];
        }

        $lote = $linhas->map(fn (Preco $preco) => [
            'id' => $preco->id,
            'catalogo_id' => $preco->catalogo_id,
            'servico_id' => $preco->servico_id,
            'consumo_minimo_cents' => $preco->consumo_minimo_cents,
            'custo_cents' => $preco->custo_cents,
            'preco_cents' => $informados[$preco->id],
        ])->values()->all();

        if ($lote !== []) {
            Preco::upsert($lote, ['id'], ['preco_cents']);
        }

        return ['gravados' => count($lote), 'furam' => 0, 'piso' => null];
    }
}
