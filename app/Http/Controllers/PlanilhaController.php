<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Preco;
use App\Models\Servico;
use App\Support\Margem;
use App\Support\Planilha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta o modulo inteiro numa planilha de tres abas e le de volta.
 *
 * Uma planilha so, e nao um arquivo por tela: quem negocia preco com o
 * fornecedor precisa ver custo, catalogo e plano lado a lado.
 */
class PlanilhaController extends Controller
{
    public function exportar(): StreamedResponse
    {
        $catalogo = Catalogo::vigente();
        $nome = 'avalia-catalogo-'.now()->format('Y-m-d').'.xlsx';

        $conteudo = Planilha::xlsx([
            'Catalogo' => $this->abaCatalogo($catalogo),
            'Planos' => $this->abaPlanos(),
            'Servicos' => $this->abaServicos(),
        ]);

        return response()->streamDownload(fn () => print $conteudo, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Le a aba de catalogo e grava preco e custo.
     *
     * Casa pelo codigo do servico e pelo titulo da coluna, e nao pela posicao:
     * quem edita no Excel move coluna, e quebrar por isso seria armadilha.
     * Linha de servico que nao existe e ignorada, nao criada, porque criar
     * servico exige decisao comercial.
     */
    public function importar(Request $request)
    {
        $dados = $request->validate([
            'planilha' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:4096'],
        ]);

        $catalogo = Catalogo::vigente();

        if (! $catalogo) {
            return back()->with('erro', 'Nao ha catalogo para receber a importacao.');
        }

        $linhas = Planilha::ler($dados['planilha']->getRealPath());

        if (count($linhas) < 2) {
            return back()->with('erro', 'Planilha vazia ou sem cabecalho.');
        }

        $colunas = array_map(fn ($t) => mb_strtolower(trim((string) $t)), array_shift($linhas));
        $ondeEsta = fn (string $titulo) => array_search($titulo, $colunas, true);

        $colCodigo = $ondeEsta('codigo');
        $colCusto = $ondeEsta('custo');

        if ($colCodigo === false) {
            return back()->with('erro', 'A planilha precisa da coluna "codigo".');
        }

        // Cada faixa vira uma coluna com o proprio titulo em reais.
        $faixaDaColuna = [];

        foreach ($colunas as $indice => $titulo) {
            foreach ($catalogo->faixas() as $faixa) {
                if ($titulo === self::tituloDaFaixa($faixa)) {
                    $faixaDaColuna[$indice] = $faixa;
                }
            }
        }

        $idPorCodigo = Servico::pluck('id', 'codigo');
        $precos = $catalogo->precos()->get()->keyBy(fn (Preco $p) => $p->servico_id.':'.$p->consumo_minimo_cents);

        $mudancas = [];
        $ignorados = 0;

        foreach ($linhas as $linha) {
            $codigo = trim((string) ($linha[$colCodigo] ?? ''));

            if ($codigo === '' || ! $idPorCodigo->has($codigo)) {
                $ignorados += $codigo === '' ? 0 : 1;

                continue;
            }

            $servicoId = $idPorCodigo[$codigo];

            foreach ($faixaDaColuna as $indice => $faixa) {
                $preco = $precos->get($servicoId.':'.$faixa);
                $valor = self::centavos($linha[$indice] ?? null);

                if ($preco && $valor !== null && $valor !== $preco->preco_cents) {
                    $mudancas[$preco->id]['preco_cents'] = $valor;
                }
            }

            if ($colCusto !== false) {
                $custo = self::centavos($linha[$colCusto] ?? null);

                foreach ($catalogo->faixas() as $faixa) {
                    $preco = $precos->get($servicoId.':'.$faixa);

                    if ($preco && $custo !== $preco->custo_cents) {
                        $mudancas[$preco->id]['custo_cents'] = $custo;
                    }
                }
            }
        }

        if ($mudancas === []) {
            return back()->with('ok', 'Planilha lida: nenhum valor diferente do que ja esta cadastrado.');
        }

        DB::transaction(function () use ($mudancas, $precos) {
            foreach ($mudancas as $id => $campos) {
                Preco::whereKey($id)->update($campos);
            }

            unset($precos);
        });

        return back()->with('ok', sprintf(
            '%d preco(s) atualizados pela planilha.%s',
            count($mudancas),
            $ignorados > 0 ? " {$ignorados} linha(s) com codigo desconhecido foram ignoradas." : '',
        ));
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function abaCatalogo(?Catalogo $catalogo): array
    {
        if (! $catalogo) {
            return [['codigo'], []];
        }

        $faixas = $catalogo->faixas();
        $comissao = $catalogo->comissaoBps();

        $cabecalho = array_merge(
            ['codigo', 'servico', 'categoria', 'situacao', 'custo'],
            array_map(self::tituloDaFaixa(...), $faixas),
            ['margem menor faixa', 'margem maior faixa'],
        );

        $linhas = [];

        foreach ($catalogo->precos()->with('servico')->get()->groupBy('servico_id') as $precos) {
            $servico = $precos->first()->servico;
            $porFaixa = $precos->keyBy('consumo_minimo_cents');
            $custo = $precos->first()->custo_cents;

            $margem = function (int $faixa) use ($porFaixa, $catalogo, $comissao): ?float {
                $preco = $porFaixa->get($faixa);

                return $preco
                    ? Margem::pct($preco->preco_cents, $preco->custo_cents, $catalogo->imposto_bps, $comissao)
                    : null;
            };

            $linhas[] = array_merge(
                [
                    $servico->codigo,
                    $servico->nome,
                    $servico->rotuloCategoria(),
                    self::situacao($servico),
                    self::reais($custo),
                ],
                array_map(fn (int $f) => self::reais($porFaixa->get($f)?->preco_cents), $faixas),
                [$margem($faixas[0] ?? 0), $margem($faixas[count($faixas) - 1] ?? 0)],
            );
        }

        return [$cabecalho, $linhas];
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function abaPlanos(): array
    {
        $linhas = Plano::with('catalogo')
            ->orderBy('consumo_minimo_cents')
            ->get()
            ->map(fn (Plano $plano) => [
                $plano->nome,
                self::reais($plano->consumo_minimo_cents),
                self::reais($plano->mensalidade_cents),
                self::reais($plano->faturaMinimaCents()),
                $plano->ativo ? 'ativo' : 'inativo',
                $plano->franquias()->count(),
            ])
            ->all();

        return [['plano', 'consumo minimo', 'mensalidade', 'fatura minima', 'situacao', 'servicos com franquia'], $linhas];
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function abaServicos(): array
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

    private static function situacao(Servico $servico): string
    {
        return match (true) {
            ! $servico->ativo => 'pausado',
            $servico->exige_liberacao => 'aguarda liberacao',
            default => 'disponivel',
        };
    }

    /** Centavos viram numero com duas casas, para o Excel poder somar a coluna. */
    private static function reais(?int $centavos): ?float
    {
        return $centavos === null ? null : round($centavos / 100, 2);
    }

    /** Aceita "5,46", "5.46" e "R$ 5,46"; devolve null para celula vazia. */
    private static function centavos(mixed $valor): ?int
    {
        return \App\Support\Dinheiro::paraCentavos(is_string($valor) || is_numeric($valor) ? (string) $valor : null);
    }

    private static function tituloDaFaixa(int $faixa): string
    {
        return $faixa === 0 ? 'sem minimo' : 'faixa '.number_format($faixa / 100, 2, ',', '.');
    }
}
