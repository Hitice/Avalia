<?php

namespace App\Actions\Catalogo;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Models\Servico;
use App\Support\Dinheiro;
use App\Support\Margem;
use Illuminate\Support\Facades\DB;

/**
 * Grava tudo de um servico numa tacada: cadastro, custo e precos por faixa.
 *
 * Uma pagina por servico em vez de uma matriz de 301 campos. O operador mexe
 * numa linha por vez, ve a margem do que digitou e salva so aquilo.
 *
 * Recusa o lote inteiro se algum preco furar o piso: gravar so os validos
 * deixaria o operador achando que salvou tudo.
 */
class GravarServicoCompleto
{
    /**
     * @param  array<int|string, string|null>  $precosPorFaixa  faixa em centavos => valor digitado
     * @return array{piso: int|null} piso preenchido quando algum preco foi recusado
     */
    public function __invoke(
        ?Catalogo $catalogo,
        Servico $servico,
        array $cadastro,
        ?string $custo,
        array $precosPorFaixa,
    ): array {
        // Sem catalogo nao ha preco nem custo, mas nome, categoria e situacao
        // sao do servico: renomear nao pode depender da tabela de precos existir.
        if (! $catalogo) {
            $servico->update($cadastro);

            return ['piso' => null];
        }

        $custoCents = Dinheiro::paraCentavos($custo);
        $custoCents = $custoCents !== null && $custoCents < 0 ? null : $custoCents;

        $precos = $catalogo->precos()->where('servico_id', $servico->id)->get();

        $novos = collect($precosPorFaixa)
            ->map(fn ($valor) => Dinheiro::paraCentavos($valor))
            ->reject(fn (?int $centavos) => $centavos === null || $centavos < 0);

        $furam = $precos->filter(fn (Preco $preco) => $novos->has($preco->consumo_minimo_cents)
            && Margem::daPrejuizo(
                $novos[$preco->consumo_minimo_cents], $custoCents,
                $catalogo->imposto_bps, $catalogo->comissaoBps(),
            ));

        if ($furam->isNotEmpty()) {
            return ['piso' => Margem::pisoCents($custoCents, $catalogo->imposto_bps, $catalogo->comissaoBps())];
        }

        DB::transaction(function () use ($servico, $cadastro, $precos, $novos, $custoCents) {
            $servico->update($cadastro);

            $lote = $precos->map(fn (Preco $preco) => [
                'id' => $preco->id,
                'catalogo_id' => $preco->catalogo_id,
                'servico_id' => $preco->servico_id,
                'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                'preco_cents' => $novos[$preco->consumo_minimo_cents] ?? $preco->preco_cents,
                'custo_cents' => $custoCents,
            ])->values()->all();

            if ($lote !== []) {
                Preco::upsert($lote, ['id'], ['preco_cents', 'custo_cents']);
            }
        });

        return ['piso' => null];
    }
}
