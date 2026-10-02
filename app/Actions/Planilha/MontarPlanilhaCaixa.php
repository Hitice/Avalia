<?php

namespace App\Actions\Planilha;

use App\Contabil\LivroCaixa;
use App\Models\LancamentoFinanceiro;
use App\Support\Planilha;
use Illuminate\Support\Collection;

/** O livro-caixa do mes em duas abas: os movimentos com saldo, e o por categoria. */
class MontarPlanilhaCaixa
{
    /** @param  Collection<int, LancamentoFinanceiro>  $doMes */
    public function __invoke(Collection $doMes, string $competencia): string
    {
        $caixa = LivroCaixa::doMes($doMes, $competencia);

        return Planilha::xlsx([
            'Caixa '.$competencia => [
                ['Quando', 'Natureza', 'Descrição', 'Categoria', 'Contraparte', 'Entrada', 'Saída', 'Saldo', 'Lançado por'],
                $caixa['movimentos']->reverse()->map(fn (array $m) => [
                    $m['lancamento']->ocorrido_em->format('d/m/Y'),
                    $m['lancamento']->natureza->rotulo(),
                    $m['lancamento']->descricao,
                    $m['categoria'],
                    $m['lancamento']->contraparte ?? '',
                    MontarPlanilhaFaturas::reais($m['entrada'] ?: null),
                    MontarPlanilhaFaturas::reais($m['saida'] ?: null),
                    MontarPlanilhaFaturas::reais($m['saldo']),
                    $m['lancamento']->staff?->nome ?? '',
                ])->values()->all(),
            ],
            'Por categoria' => [
                ['Categoria', 'Grupo', 'Valor'],
                LivroCaixa::porCategoria($doMes)->map(fn (array $l) => [
                    $l['nome'], $l['grupo'] === 'receita' ? 'Receita' : 'Despesa', MontarPlanilhaFaturas::reais($l['cents']),
                ])->all(),
            ],
        ]);
    }
}
