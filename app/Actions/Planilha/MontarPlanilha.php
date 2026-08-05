<?php

namespace App\Actions\Planilha;

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Servico;
use App\Support\Margem;
use App\Support\Planilha;
use Illuminate\Support\Collection;

/**
 * Monta a planilha do modulo: catalogo, planos e servicos em tres abas.
 *
 * Uma planilha so, e nao um arquivo por tela: quem negocia preco com o
 * fornecedor precisa ver custo, catalogo e plano lado a lado.
 */
class MontarPlanilha
{
    public function __invoke(): string
    {
        return Planilha::xlsx([
            'Catalogo' => $this->catalogo(Catalogo::vigente()),
            'Planos' => $this->planos(),
            'Servicos' => $this->servicos(),
        ]);
    }

    /** Titulo da coluna de faixa, que e tambem a chave usada na importacao. */
    public static function tituloDaFaixa(int $faixa): string
    {
        return $faixa === 0 ? 'sem minimo' : 'faixa '.number_format($faixa / 100, 2, ',', '.');
    }

    public static function situacao(Servico $servico): string
    {
        return match (true) {
            ! $servico->ativo => 'pausado',
            $servico->exige_liberacao => 'aguarda liberacao',
            default => 'disponivel',
        };
    }

    /** Centavos viram numero com duas casas, para o Excel poder somar a coluna. */
    public static function reais(?int $centavos): ?float
    {
        return $centavos === null ? null : round($centavos / 100, 2);
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function catalogo(?Catalogo $catalogo): array
    {
        if (! $catalogo) {
            return [['codigo'], []];
        }

        $faixas = $catalogo->faixas();
        $comissao = $catalogo->comissaoBps();

        $cabecalho = array_merge(
            ['codigo', 'servico', 'categoria', 'situacao', 'custo'],
            array_map(self::tituloDaFaixa(...), $faixas),
            ['margem menor faixa (%)', 'margem maior faixa (%)'],
        );

        $linhas = $catalogo->precos()
            ->with('servico')
            ->get()
            ->groupBy('servico_id')
            ->map(function (Collection $precos) use ($faixas, $catalogo, $comissao) {
                $servico = $precos->first()->servico;
                $porFaixa = $precos->keyBy('consumo_minimo_cents');

                $margem = fn (int $faixa) => ($p = $porFaixa->get($faixa))
                    ? Margem::pct($p->preco_cents, $p->custo_cents, $catalogo->imposto_bps, $comissao)
                    : null;

                return array_merge(
                    [
                        $servico->codigo,
                        $servico->nome,
                        $servico->rotuloCategoria(),
                        self::situacao($servico),
                        self::reais($precos->first()->custo_cents),
                    ],
                    array_map(fn (int $f) => self::reais($porFaixa->get($f)?->preco_cents), $faixas),
                    [$margem($faixas[0] ?? 0), $margem($faixas[count($faixas) - 1] ?? 0)],
                );
            })
            ->values()
            ->all();

        return [$cabecalho, $linhas];
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function planos(): array
    {
        $linhas = Plano::withCount('franquias')
            ->orderBy('consumo_minimo_cents')
            ->get()
            ->map(fn (Plano $plano) => [
                $plano->nome,
                self::reais($plano->consumo_minimo_cents),
                self::reais($plano->mensalidade_cents),
                self::reais($plano->faturaMinimaCents()),
                $plano->ativo ? 'ativo' : 'inativo',
                $plano->franquias_count,
            ])
            ->all();

        return [
            ['plano', 'consumo minimo', 'mensalidade', 'fatura minima', 'situacao', 'servicos com franquia'],
            $linhas,
        ];
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function servicos(): array
    {
        $linhas = Servico::withCount('precos')
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->map(fn (Servico $servico) => [
                $servico->codigo,
                $servico->nome,
                $servico->rotuloCategoria(),
                self::situacao($servico),
                $servico->precos_count,
            ])
            ->all();

        return [['codigo', 'servico', 'categoria', 'situacao', 'precos'], $linhas];
    }
}
