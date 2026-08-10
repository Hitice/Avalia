<?php

namespace App\Http\Controllers;

use App\Actions\Financeiro\EstornarLiquidacao;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Mail\FaturaEmitida;
use App\Models\Fatura;
use App\Models\Staff;
use App\Support\Auditar;
use App\Support\FiltroFaturas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $escolha = FiltroFaturas::escolhido($request);

        return view('paginas.financeiro.index', [
            'faturas' => $this->recorte($request)->get(),
            'escolha' => $escolha,
            'situacao' => $escolha['situacao'] ?: null,
            // O que o RECORTE soma, ao lado do que a operacao inteira soma: sem
            // isso o operador filtra, olha o cartao do topo e acha que o filtro
            // nao pegou.
            'resumo' => FiltroFaturas::resumo($this->recorte($request)),
            'totais' => $this->totais(),
            'comissoes' => $this->comissoesPorVendedor(),
            'vendedores' => Staff::where('papel', 'vendedor')->orderBy('nome')->get(),
            'competencias' => Fatura::query()->select('competencia')->distinct()
                ->orderByDesc('competencia')->pluck('competencia'),
        ]);
    }

    /**
     * As faturas do recorte, em planilha, para conciliar fora do sistema.
     *
     * Leva numero interno de proposito, e e a unica exportacao do projeto que
     * leva: e o arquivo do contador e da conciliacao bancaria, e sem custo e
     * comissao ele nao serve para nenhuma das duas coisas. O nome do arquivo
     * avisa, e a trilha guarda quem levou.
     */
    public function exportar(Request $request, \App\Actions\Planilha\MontarPlanilhaFaturas $montar): StreamedResponse
    {
        $faturas = $this->recorte($request)->get();
        $conteudo = $montar($faturas);

        Auditar::registrar('faturas.exportadas', null, ['faturas' => $faturas->count()]);

        return response()->streamDownload(
            fn () => print $conteudo,
            'avalia-faturas-interno-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Reenvia o aviso de fatura para o e-mail do cliente.
     *
     * O e-mail original se perde na caixa de entrada, e ligar para pedir que
     * procurem custa mais do que mandar de novo. E o mesmo e-mail do
     * fechamento, com o mesmo botao: reenvio que muda o texto vira uma segunda
     * versao da cobranca para o cliente comparar.
     */
    public function reenviar(Fatura $fatura)
    {
        if ($fatura->estaLiquidada()) {
            return back()->with('erro', 'Esta fatura já está paga.');
        }

        if (! $fatura->cliente?->email) {
            return back()->with('erro', 'Este cliente não tem e-mail cadastrado.');
        }

        try {
            Mail::to($fatura->cliente->email)->send(new FaturaEmitida($fatura));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('erro', 'O e-mail não pôde ser enviado agora. Tente de novo em instantes.');
        }

        Auditar::registrar('fatura.reenviada', $fatura, ['email' => $fatura->cliente->email]);

        return back()->with('ok', 'Cobrança reenviada para '.$fatura->cliente->email.'.');
    }

    /**
     * As acoes que valem para varias faturas de uma vez.
     *
     * No fechamento a operacao repete a mesma coisa dezenas de vezes, e uma a
     * uma cansa e erra. O que NAO entra aqui e a baixa de pagamento: ela exige
     * justificativa por fatura e libera comissao, e uma baixa em lote seria a
     * porta mais larga do sistema para dinheiro dado como recebido sem ter
     * entrado.
     */
    public function lote(Request $request)
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:reenviar,exportar'],
            'faturas' => ['required', 'array', 'min:1'],
            'faturas.*' => ['integer'],
        ], [
            'faturas.required' => 'Selecione ao menos uma fatura.',
        ]);

        $faturas = Fatura::with(['cliente', 'vendedor'])->whereIn('id', $dados['faturas'])->get();

        if ($dados['acao'] === 'exportar') {
            $conteudo = app(\App\Actions\Planilha\MontarPlanilhaFaturas::class)($faturas);

            Auditar::registrar('faturas.exportadas', null, ['faturas' => $faturas->count()]);

            return response()->streamDownload(
                fn () => print $conteudo,
                'avalia-faturas-interno-'.now()->format('Y-m-d').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            );
        }

        $enviados = 0;

        foreach ($faturas as $fatura) {
            if ($fatura->estaLiquidada() || ! $fatura->cliente?->email) {
                continue;
            }

            try {
                Mail::to($fatura->cliente->email)->send(new FaturaEmitida($fatura));
                Auditar::registrar('fatura.reenviada', $fatura, ['email' => $fatura->cliente->email]);
                $enviados++;
            } catch (\Throwable $e) {
                // Uma falha nao pode derrubar o lote: o provedor recusa um
                // endereco e os outros continuam merecendo o aviso.
                report($e);
            }
        }

        return back()->with($enviados > 0 ? 'ok' : 'erro', $enviados > 0
            ? $enviados.' '.($enviados === 1 ? 'cobrança reenviada' : 'cobranças reenviadas').'.'
            : 'Nenhuma cobrança foi reenviada. Faturas pagas e clientes sem e-mail ficam de fora.');
    }

    /** Um lugar so decide o recorte, para tela, resumo e planilha baterem. */
    private function recorte(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $faturas = Fatura::query()->with(['cliente', 'vendedor', 'cobrancaAsaas']);

        return FiltroFaturas::aplicar($faturas, $request)
            ->orderByDesc('competencia')
            ->orderBy('cliente_id');
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
