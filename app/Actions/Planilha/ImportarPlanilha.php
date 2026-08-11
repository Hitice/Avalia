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
    /** @return array{erro: string|null, atualizados: int, produtos: int, ignorados: int} */
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
        $colProduto = array_search('produto no fornecedor', $colunas, true);
        $colFornecedor = array_search('fornecedor', $colunas, true);

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
        $produtos = [];
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

            // O produto do fornecedor vem pela planilha porque sao dezenas de
            // servicos e a alternativa e abrir uma tela por servico. Celula
            // vazia NAO apaga o que ja esta gravado: quem exporta, mexe em
            // preco e reimporta nao pode desligar a consulta real sem querer.
            if ($colProduto !== false) {
                $produto = trim((string) ($linha[$colProduto] ?? ''));

                if ($produto !== '') {
                    $produtos[$servicoId]['codigo_fornecedor'] = $produto;
                }
            }

            // Fornecedor desconhecido e ignorado em vez de gravado: nome errado
            // na planilha nao pode desviar consulta para um conector que nao
            // existe, e a linha continua no fornecedor que ja tinha.
            if ($colFornecedor !== false) {
                $fornecedor = trim(mb_strtolower((string) ($linha[$colFornecedor] ?? '')));

                if (array_key_exists($fornecedor, \App\Services\Conectores\EscolherConector::CONECTORES)) {
                    $produtos[$servicoId]['fornecedor'] = $fornecedor;
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

        $servicosMudados = 0;

        DB::transaction(function () use ($mudancas, $produtos, &$servicosMudados) {
            foreach ($mudancas as $id => $campos) {
                Preco::whereKey($id)->update($campos);
            }

            foreach ($produtos as $servicoId => $campos) {
                $servico = Servico::find($servicoId);

                if (! $servico) {
                    continue;
                }

                $diferentes = array_filter($campos, fn ($valor, $campo) => $servico->{$campo} !== $valor, ARRAY_FILTER_USE_BOTH);

                if ($diferentes !== []) {
                    $servico->update($diferentes);
                    $servicosMudados++;
                }
            }
        });

        return [
            'erro' => null,
            'atualizados' => count($mudancas),
            'produtos' => $servicosMudados,
            'ignorados' => $ignorados,
        ];
    }

    /** @return array{erro: string, atualizados: int, produtos: int, ignorados: int} */
    private function falha(string $mensagem): array
    {
        return ['erro' => $mensagem, 'atualizados' => 0, 'produtos' => 0, 'ignorados' => 0];
    }

    /** Aceita "5,46", "5.46" e "R$ 5,46"; devolve null para celula vazia. */
    private function centavos(mixed $valor): ?int
    {
        return Dinheiro::paraCentavos(is_string($valor) || is_numeric($valor) ? (string) $valor : null);
    }
}
