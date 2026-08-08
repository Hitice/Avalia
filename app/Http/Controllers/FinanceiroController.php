<?php

namespace App\Http\Controllers;

use App\Actions\Financeiro\EstornarLiquidacao;
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
 * A baixa passa por RegistrarLiquidacao, e nao grava a fatura direto: liberar
 * comissao e regra de negocio, e ela precisa valer igual venha o pagamento de
 * onde vier.
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

    /**
     * Confirma o pagamento a mao, e exige dizer por que.
     *
     * Esta e a unica porta pela qual dinheiro e dado como recebido sem que
     * dinheiro nenhum tenha entrado. Ela libera a comissao do vendedor na
     * mesma hora, e desfazer depois significa cobrar de volta alguem que ja
     * recebeu. Por isso o motivo e obrigatorio e vai inteiro para a trilha:
     * quem confirmou precisa poder ser perguntado meses depois.
     */
    public function liquidar(Request $request, Fatura $fatura, RegistrarLiquidacao $liquidar)
    {
        if ($fatura->estaLiquidada()) {
            return back()->with('erro', 'Esta fatura já estava liquidada.');
        }

        $dados = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:255']],
            ['motivo.required' => 'Diga como o pagamento foi conferido.',
                'motivo.min' => 'Descreva com mais detalhe: :min caracteres no mínimo.'],
        );

        $liquidar($fatura, null, RegistrarLiquidacao::ORIGEM_MANUAL, $dados['motivo']);

        return back()->with('ok', sprintf(
            'Fatura de %s liquidada. %s',
            $fatura->cliente->razao_social,
            $fatura->comissao_cents > 0 && $fatura->vendedor_id
                ? 'Comissão liberada para repasse.'
                : 'Sem comissão a liberar.',
        ));
    }

    /**
     * Desfaz um recebimento e recolhe a comissao liberada.
     *
     * Pagamento desfeito acontece: chargeback, Pix devolvido, boleto baixado
     * por engano. Sem este caminho, o dinheiro do vendedor ja tinha saido do
     * controle sem volta, e a correcao so existia no banco.
     */
    public function estornar(Request $request, Fatura $fatura, EstornarLiquidacao $estornar)
    {
        $dados = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:255']],
            ['motivo.required' => 'Diga por que o recebimento foi desfeito.',
                'motivo.min' => 'Descreva com mais detalhe: :min caracteres no mínimo.'],
        );

        $resultado = $estornar($fatura, $dados['motivo']);

        if ($resultado['erro']) {
            return back()->with('erro', $resultado['erro']);
        }

        return back()->with('ok', sprintf(
            'Recebimento de %s desfeito. %s',
            $fatura->cliente->razao_social,
            $fatura->comissao_cents > 0 ? 'A comissão foi recolhida.' : 'Não havia comissão liberada.',
        ));
    }

    /** O mesmo demonstrativo que o cliente baixa, para o atendimento. */
    public function demonstrativo(Fatura $fatura)
    {
        return \App\Support\FaturaPdf::resposta($fatura->load('itens', 'cliente'));
    }

    /**
     * Emite (ou reemite) a cobranca de uma fatura no provedor.
     *
     * O fechamento ja tenta criar a cobranca e engole a falha de proposito,
     * para nao derrubar a competencia inteira por causa do provedor fora do ar.
     * O que faltava era o caminho de volta: sem este botao, fatura sem boleto
     * so se resolvia no banco de dados.
     */
    public function emitirCobranca(Fatura $fatura, \App\Actions\Financeiro\CriarCobrancaAsaas $criar)
    {
        if ($fatura->estaLiquidada()) {
            return back()->with('erro', 'Esta fatura já está paga.');
        }

        if ($fatura->cobrancaAsaas?->asaas_charge_id) {
            return back()->with('erro', 'Esta fatura já tem cobrança emitida no provedor.');
        }

        try {
            $cobranca = $criar($fatura);
        } catch (\Throwable $e) {
            report($e);

            // A recusa do provedor vira texto na tela: "nao respondeu" mandava
            // o atendimento procurar no log um erro que a resposta explicava.
            return back()->with('erro', 'O provedor de cobrança recusou: '.$e->getMessage());
        }

        return back()->with($cobranca->asaas_charge_id ? 'ok' : 'erro', $cobranca->asaas_charge_id
            ? 'Cobrança emitida no provedor.'
            : 'A conexão de cobrança não está configurada e ativa. Confira em Conexões.');
    }

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
