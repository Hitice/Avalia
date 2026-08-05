<?php

namespace App\Actions\Planilha;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Models\Servico;
use App\Support\Dinheiro;
use App\Support\Planilha;
use Illuminate\Support\Facades\DB;

/**
 * Le a aba de catalogo e grava preco e custo.
 *
 * Casa pelo codigo do servico e pelo TITULO da coluna, nunca pela posicao:
 * quem edita no Excel move coluna, e quebrar por isso seria armadilha. Linha
 * com codigo desconhecido e ignorada, e nao criada, porque criar servico e
 * decisao comercial e nao efeito colateral de importacao.
 */
class ImportarPlanilha
{
    /** @return array{erro: string|null, atualizados: int, ignorados: int} */
    public function __invoke(Catalogo $catalogo, string $caminho): array
    {
        $linhas = Planilha::ler($caminho);

        if (count($linhas) < 2) {
            return $this->falha('Planilha vazia ou sem cabecalho.');
        }

        // Compara pela forma canonica: o cabecalho e escrito acentuado para
        // pessoa ler, e quem edita no Excel pode redigitar sem acento.
        $colunas = array_map(MontarPlanilha::chaveDaColuna(...), array_shift($linhas));
        $colCodigo = array_search('codigo', $colunas, true);
        $colCusto = array_search('custo', $colunas, true);

        if ($colCodigo === false) {
            return $this->falha('A planilha precisa da coluna "Código".');
        }

        $faixas = $catalogo->faixas();
        $faixaDaColuna = [];

        foreach ($colunas as $indice => $titulo) {
            foreach ($faixas as $faixa) {
                if ($titulo === MontarPlanilha::chaveDaColuna(MontarPlanilha::tituloDaFaixa($faixa))) {
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

            if ($codigo === '') {
                continue;
            }

            if (! $idPorCodigo->has($codigo)) {
                $ignorados++;

                continue;
            }

            $servicoId = $idPorCodigo[$codigo];

            foreach ($faixaDaColuna as $indice => $faixa) {
                $preco = $precos->get($servicoId.':'.$faixa);
                $valor = $this->centavos($linha[$indice] ?? null);

                if ($preco && $valor !== null && $valor !== $preco->preco_cents) {
                    $mudancas[$preco->id]['preco_cents'] = $valor;
                }
            }

            if ($colCusto !== false) {
                $custo = $this->centavos($linha[$colCusto] ?? null);

                foreach ($faixas as $faixa) {
                    $preco = $precos->get($servicoId.':'.$faixa);

                    if ($preco && $custo !== $preco->custo_cents) {
                        $mudancas[$preco->id]['custo_cents'] = $custo;
                    }
                }
            }
        }

        DB::transaction(function () use ($mudancas) {
            foreach ($mudancas as $id => $campos) {
                Preco::whereKey($id)->update($campos);
            }
        });

        return ['erro' => null, 'atualizados' => count($mudancas), 'ignorados' => $ignorados];
    }

    /** @return array{erro: string, atualizados: int, ignorados: int} */
    private function falha(string $mensagem): array
    {
        return ['erro' => $mensagem, 'atualizados' => 0, 'ignorados' => 0];
    }

    /** Aceita "5,46", "5.46" e "R$ 5,46"; devolve null para celula vazia. */
    private function centavos(mixed $valor): ?int
    {
        return Dinheiro::paraCentavos(is_string($valor) || is_numeric($valor) ? (string) $valor : null);
    }
}
