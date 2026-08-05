<?php

namespace App\Http\Controllers;

use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Fatura;
use App\Models\Staff;
use Illuminate\Http\Request;

/**
 * As faturas de todas as empresas, em um lugar so.
 *
 * A ficha da empresa responde "quanto esta empresa deve"; esta tela responde
 * "quanto a Avalia tem a receber e quanto ja deve de comissao", que e a
 * pergunta do fechamento do mes.
 *
 * A liquidacao registrada aqui e a mesma que o provedor de cobranca dispara por
 * webhook: uma acao so, para baixa manual e baixa automatica nao divergirem.
 */
class FinanceiroController extends Controller
{
    public function index(Request $request)
    {
        $situacao = in_array($request->query('situacao'), Fatura::SITUACOES_PAGAMENTO, true)
            ? $request->query('situacao')
            : null;

        $faturas = Fatura::query()
            ->with(['cliente', 'vendedor'])
            ->when($situacao, fn ($q) => $q->where('situacao_pagamento', $situacao))
            ->orderByDesc('competencia')
            ->orderBy('cliente_id')
            ->get();

        return view('paginas.financeiro.index', [
            'faturas' => $faturas,
            'situacao' => $situacao,
            'totais' => $this->totais(),
            'comissoes' => $this->comissoesPorVendedor(),
        ]);
    }

    public function liquidar(Fatura $fatura, RegistrarLiquidacao $liquidar)
    {
        if ($fatura->estaLiquidada()) {
            return back()->with('erro', 'Esta fatura já estava liquidada.');
        }

        $liquidar($fatura);

        return back()->with('ok', sprintf(
            'Fatura de %s liquidada. %s',
            $fatura->cliente->razao_social,
            $fatura->comissao_cents > 0 && $fatura->vendedor_id
                ? 'Comissão liberada para repasse.'
                : 'Sem comissão a liberar.',
        ));
    }

    /** @return array<string, int> */
    private function totais(): array
    {
        $soma = fn (array $situacoes) => (int) Fatura::whereIn('situacao_pagamento', $situacoes)->sum('total_cents');

        return [
            'a_receber' => $soma([Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO]),
            'vencido' => $soma([Fatura::PAGAMENTO_VENCIDO]),
            'liquidado' => $soma([Fatura::PAGAMENTO_LIQUIDADO]),
        ];
    }

    /**
     * Comissao liberada por vendedor, ou seja, so a de fatura ja paga.
     *
     * Cliente que nao pagou nao gera comissao: o vendedor aguarda a liquidacao
     * (PDD.md, secao 9). Somar comissao de fatura em aberto mostraria ao
     * vendedor um dinheiro que ainda nao existe.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function comissoesPorVendedor()
    {
        return Fatura::query()
            ->selectRaw('vendedor_id, count(*) as faturas, sum(comissao_cents) as total')
            ->whereNotNull('vendedor_id')
            ->whereNotNull('comissao_liberada_em')
            ->groupBy('vendedor_id')
            ->get()
            ->map(fn ($linha) => (object) [
                'vendedor' => Staff::find($linha->vendedor_id),
                'faturas' => (int) $linha->faturas,
                'total_cents' => (int) $linha->total,
            ])
            ->filter(fn ($linha) => $linha->vendedor !== null)
            ->sortByDesc('total_cents')
            ->values();
    }
}
