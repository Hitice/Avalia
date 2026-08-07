<?php

namespace App\Http\Controllers;

use App\Actions\Consumo\ApurarCompetencia;
use App\Actions\Consumo\ExecutarConsulta;
use App\Actions\Documentos\RegistrarAceiteDocumento;
use App\Models\AceiteDocumento;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Operador;
use App\Models\Servico;
use App\Support\Dinheiro;
use App\Support\DocumentoPdf;
use App\Support\FiltroConsultas;
use Illuminate\Http\Request;

/**
 * Area do cliente, um assunto por tela.
 *
 * Painel responde "como estou", Consultar faz, Consultas presta contas, Faturas
 * cobra e Documentos formaliza. Era tudo uma pagina so, o que funcionava
 * enquanto o cliente tinha meia duzia de consultas: passa disso e quem entra
 * para pagar a fatura rola a tela inteira ate achar.
 *
 * Toda leitura parte de `auth('empresa')->user()`, e nao de parametro de rota:
 * nao existe endereco que peca os dados de outra empresa.
 */
class AreaClienteController extends Controller
{
    /** Quantas consultas o painel mostra antes de mandar para a lista cheia. */
    private const ULTIMAS_NO_PAINEL = 5;

    private const POR_PAGINA = 25;

    /**
     * Visao geral: o que a empresa precisa saber sem clicar em nada.
     *
     * O que entra aqui e o que exige acao ou explica cobranca. O resto tem tela
     * propria, e o painel so aponta para ela.
     */
    public function painel()
    {
        $empresa = auth('empresa')->user();
        $competencia = Consulta::competenciaDe();
        $plano = $empresa->plano?->load('franquias.servico');

        $uso = $empresa->consultas()
            ->where('competencia', $competencia)
            ->selectRaw('servico_id, count(*) as quantidade')
            ->groupBy('servico_id')
            ->pluck('quantidade', 'servico_id');

        // Consumo do mes corrente pelo preco congelado em cada consulta, que e a
        // mesma base do fechamento: a tela e a fatura tem que fechar no centavo.
        $consumoCents = (int) $empresa->consultas()
            ->where('competencia', $competencia)
            ->where('situacao', Consulta::SUCESSO)
            ->sum('preco_cents');

        // Quanto falta para o consumo minimo, pela mesma conta do fechamento:
        // o que conta para o minimo e o excedente da franquia, nao o bruto.
        // Surpresa de fatura minima e a reclamacao mais evitavel que existe.
        $faltaMinimoCents = null;

        if ($plano && $plano->consumo_minimo_cents > 0) {
            $apurado = app(ApurarCompetencia::class)($empresa, $competencia);
            $faltaMinimoCents = max(0, $plano->consumo_minimo_cents - $apurado['excedente']);
        }

        $emAberto = $empresa->faturas()
            ->with('cobrancaAsaas')
            ->where('situacao_pagamento', '!=', 'liquidado')
            ->orderBy('competencia')
            ->get();

        $pendentes = $this->documentosPendentes($empresa);

        $ultimas = $empresa->consultas()
            ->with('servico')
            ->latest('id')
            ->limit(self::ULTIMAS_NO_PAINEL)
            ->get();

        return view('paginas.empresa.painel', compact(
            'empresa', 'competencia', 'plano', 'uso', 'consumoCents', 'faltaMinimoCents', 'emAberto', 'pendentes', 'ultimas',
        ));
    }

    /** O formulario de consulta, sozinho na tela. */
    public function consultar()
    {
        $empresa = auth('empresa')->user();
        $plano = $empresa->plano;
        $servicos = $plano?->servicosDisponiveis() ?? collect();

        // Consultas concluidas do mes por servico: e o que ocupa franquia.
        $uso = $empresa->consultas()
            ->where('competencia', Consulta::competenciaDe())
            ->where('situacao', Consulta::SUCESSO)
            ->selectRaw('servico_id, count(*) as quantidade')
            ->groupBy('servico_id')
            ->pluck('quantidade', 'servico_id');

        // O preco de cada servico vai junto do nome: ninguem deveria descobrir
        // o valor da consulta na fatura. A franquia aparece quando existe.
        $precos = $servicos->mapWithKeys(function ($servico) use ($plano, $uso) {
            $franquia = $plano->franquiaDe($servico->codigo);
            $usadas = (int) ($uso[$servico->id] ?? 0);

            $texto = ($servico->descricao ? $servico->descricao.' · ' : '')
                .'Valor por consulta: '.Dinheiro::brl((int) $plano->precoDe($servico->codigo));

            if ($franquia > 0) {
                $texto .= $usadas < $franquia
                    ? ' · '.($franquia - $usadas).' de '.$franquia.' consultas da franquia ainda incluídas na mensalidade'
                    : ' · As '.$franquia.' consultas da franquia deste mês já foram usadas';
            }

            return [$servico->id => $texto];
        });

        return view('paginas.empresa.consultar', [
            'empresa' => $empresa,
            'servicos' => $servicos,
            'precos' => $precos,
            'pendentes' => $this->documentosPendentes($empresa),
        ]);
    }

    /**
     * Historico completo, filtrado e paginado.
     *
     * E a tela que o cliente abre para conferir a fatura, entao o resumo do topo
     * separa tentativa de cobranca: consulta que falhou aparece na lista e nao
     * entra no valor.
     */
    public function consultas(Request $pedido)
    {
        $empresa = auth('empresa')->user();

        $filtradas = FiltroConsultas::aplicar($empresa->consultas()->getQuery(), $pedido);

        return view('paginas.empresa.consultas', [
            'empresa' => $empresa,
            'escolha' => FiltroConsultas::escolhido($pedido),
            'resumo' => FiltroConsultas::resumo($filtradas),
            'servicos' => Servico::orderBy('nome')->get(),
            'consultas' => $filtradas->with('servico')->latest('id')
                ->paginate(self::POR_PAGINA)->withQueryString(),
        ]);
    }

    public function faturas()
    {
        $empresa = auth('empresa')->user();

        return view('paginas.empresa.faturas', [
            'empresa' => $empresa,
            // Os itens vao junto: a fatura precisa se explicar linha a linha
            // sem chamado ao atendimento. Custo e margem nao existem aqui.
            'faturas' => $empresa->faturas()->with('cobrancaAsaas', 'itens')
                ->orderByDesc('competencia')->paginate(self::POR_PAGINA),
        ]);
    }

    public function documentos()
    {
        $empresa = auth('empresa')->user();

        return view('paginas.empresa.documentos', [
            'empresa' => $empresa,
            'documentos' => DocumentoLegal::query()
                ->para(Operador::daSessao() ? 'operador' : 'empresa')
                ->orderBy('titulo')->get(),
            // O "Aceito" e da identidade da sessao: o aceite da master nao
            // marca o do operador, nem o contrario.
            'aceites' => $empresa->aceitesDocumentos()
                ->where('operador_id', Operador::daSessao()?->id)
                ->pluck('documento_id')->all(),
        ]);
    }

    /**
     * A consulta em si, pedida pela empresa.
     *
     * A finalidade e obrigatoria e vai gravada junto: a secao 14 do PDD exige
     * que cada consulta tenha motivo e responsavel rastreaveis, e e o que
     * sustenta a base legal se alguem perguntar depois por que aquele CPF foi
     * consultado.
     */
    public function executar(Request $request, ExecutarConsulta $consultar)
    {
        $empresa = auth('empresa')->user();

        $dados = $request->validate([
            'servico_id' => ['required', 'integer'],
            'documento' => ['required', 'string', 'min:11', 'max:20'],
        ], [
            'documento.min' => 'Informe um CPF ou CNPJ completo.',
        ]);

        $servico = Servico::findOrFail($dados['servico_id']);

        // Finalidade e solicitante nao se digitam mais: a finalidade vinculante
        // e a declarada no aceite dos termos, gravada em toda consulta, e o
        // solicitante e quem esta logado. Formulario de dois campos.
        $operador = Operador::daSessao();

        $resultado = $consultar(
            $empresa, $servico, $dados['documento'], Consulta::FINALIDADE_PADRAO,
            $operador?->nome ?? ($empresa->responsavel_nome ?: null), $operador,
        );

        if ($resultado['erro']) {
            return back()->withInput()->with('erro', $resultado['erro']);
        }

        return redirect()
            ->route('empresa.consultas.ver', $resultado['consulta'])
            ->with('ok', 'Consulta concluída.');
    }

    /**
     * Simulador da fatura: quantas consultas de cada servico, quanto sai o mes.
     *
     * A mesma matematica do fechamento (franquia por servico em quantidade,
     * excedente contra o minimo), aplicada a um cenario digitado. Nada e
     * gravado e tudo vai por GET: o cenario vira link. Custo, margem e
     * comissao nao existem aqui, como em toda a area do cliente.
     */
    public function simulador(Request $pedido)
    {
        $empresa = auth('empresa')->user();
        $plano = $empresa->plano;
        $servicos = $plano?->servicosDisponiveis() ?? collect();

        $quantidades = collect($pedido->query('q', []))
            ->map(fn ($q) => max(0, min(1_000_000, (int) $q)));

        $linhas = $servicos->map(function ($servico) use ($plano, $quantidades) {
            $quantidade = (int) ($quantidades[$servico->id] ?? 0);
            $franquia = $plano->franquiaDe($servico->codigo);
            $preco = (int) $plano->precoDe($servico->codigo);
            $excedentes = max(0, $quantidade - $franquia);

            return [
                'servico' => $servico,
                'quantidade' => $quantidade,
                'preco_cents' => $preco,
                'franquia' => $franquia,
                'na_franquia' => min($quantidade, $franquia),
                'excedente_cents' => $excedentes * $preco,
            ];
        });

        $excedente = (int) $linhas->sum('excedente_cents');
        $minimo = (int) ($plano->consumo_minimo_cents ?? 0);
        $faturado = max($excedente, $minimo);

        return view('paginas.empresa.simulador', [
            'empresa' => $empresa,
            'plano' => $plano,
            'linhas' => $linhas,
            'excedente' => $excedente,
            'minimo' => $minimo,
            'faturado' => $faturado,
            'total' => (int) ($plano->mensalidade_cents ?? 0) + $faturado,
        ]);
    }

    /** O resultado de uma consulta, so para quem a pediu. */
    public function verConsulta(Consulta $consulta)
    {
        abort_unless($consulta->cliente_id === auth('empresa')->id(), 403);

        return view('paginas.empresa.consulta', ['consulta' => $consulta->load('servico')]);
    }

    /**
     * O aceite deixa de ser um clique e vira evidencia.
     *
     * Tres exigencias: o nome de quem aceita (a conta e da empresa, mas quem
     * clica e uma pessoa), a confirmacao explicita de leitura, e o hash do
     * conteudo que estava na tela. O hash fecha a janela entre ler e aceitar:
     * se o documento mudou no meio, o aceite e recusado e a pessoa rele a
     * versao vigente, em vez de aceitar as cegas um texto que nao viu.
     */
    public function aceitar(Request $pedido, DocumentoLegal $documento, RegistrarAceiteDocumento $registrar)
    {
        $dados = $pedido->validate([
            'responsavel' => ['required', 'string', 'min:5', 'max:150'],
            'li' => ['accepted'],
            'hash' => ['required', 'string'],
        ], [
            'responsavel.required' => 'Informe o nome completo de quem está aceitando.',
            'responsavel.min' => 'Informe o nome completo de quem está aceitando.',
            'li.accepted' => 'Confirme que leu o documento.',
        ]);

        if (! hash_equals($documento->hashConteudo(), $dados['hash'])) {
            return back()->with('erro', 'O documento foi atualizado desde a sua leitura. Releia a versão vigente antes de aceitar.');
        }

        // Quem esta aceitando: a conta master registra o aceite contratual,
        // o operador registra a propria ciencia. Uma linha por identidade.
        $registrar(auth('empresa')->user(), $documento, $dados['responsavel'], Operador::daSessao());

        return back()->with('ok', 'Aceite registrado. O comprovante em PDF já está disponível.');
    }

    /** O documento em PDF, para leitura e arquivo. */
    public function documentoPdf(DocumentoLegal $documento)
    {
        abort_unless($documento->ativo, 404);

        return response(DocumentoPdf::documento($documento), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documento->tipo.'-v'.$documento->versao.'.pdf"',
        ]);
    }

    /**
     * O comprovante do aceite da propria empresa: quem, quando, de onde e o
     * hash do que foi aceito. Sem aceite, nao ha o que comprovar.
     */
    public function comprovantePdf(DocumentoLegal $documento)
    {
        $aceite = AceiteDocumento::where('documento_id', $documento->id)
            ->where('cliente_id', auth('empresa')->id())
            ->where('operador_id', Operador::daSessao()?->id)
            ->firstOrFail();

        return response(DocumentoPdf::comprovante($aceite), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comprovante-'.$documento->tipo.'.pdf"',
        ]);
    }

    /**
     * Documentos que exigem aceite e ainda nao foram aceitos.
     *
     * O painel e a tela de consulta avisam antes, porque a falta trava a
     * consulta la no fundo da acao: e melhor o cliente saber na entrada do que
     * descobrir com o formulario preenchido.
     */
    private function documentosPendentes($empresa)
    {
        // Pendencia e da identidade da sessao: a master responde pelo aceite
        // contratual, cada operador pela propria ciencia.
        $aceitos = $empresa->aceitesDocumentos()
            ->where('operador_id', Operador::daSessao()?->id)
            ->pluck('documento_id')->all();

        return DocumentoLegal::query()
            ->para(Operador::daSessao() ? 'operador' : 'empresa')
            ->where('exige_aceite', true)
            ->whereNotIn('id', $aceitos)
            ->orderBy('titulo')
            ->get();
    }
}
