<?php

namespace App\Http\Controllers;

use App\Actions\Consumo\ExecutarConsulta;
use App\Actions\Documentos\RegistrarAceiteDocumento;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Servico;
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
            'empresa', 'competencia', 'plano', 'uso', 'consumoCents', 'emAberto', 'pendentes', 'ultimas',
        ));
    }

    /** O formulario de consulta, sozinho na tela. */
    public function consultar()
    {
        $empresa = auth('empresa')->user();

        return view('paginas.empresa.consultar', [
            'empresa' => $empresa,
            'servicos' => $empresa->plano?->servicosDisponiveis() ?? collect(),
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
            'faturas' => $empresa->faturas()->with('cobrancaAsaas')
                ->orderByDesc('competencia')->paginate(self::POR_PAGINA),
        ]);
    }

    public function documentos()
    {
        $empresa = auth('empresa')->user();

        return view('paginas.empresa.documentos', [
            'empresa' => $empresa,
            'documentos' => DocumentoLegal::query()->where('ativo', true)->orderBy('titulo')->get(),
            'aceites' => $empresa->aceitesDocumentos()->pluck('documento_id')->all(),
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
            'finalidade' => ['required', 'string', 'min:10', 'max:120'],
            'solicitante' => ['nullable', 'string', 'max:150'],
        ], [
            'documento.min' => 'Informe um CPF ou CNPJ completo.',
            'finalidade.required' => 'Diga para que a consulta sera usada.',
            'finalidade.min' => 'Descreva a finalidade com mais detalhe.',
        ]);

        $servico = Servico::findOrFail($dados['servico_id']);

        $resultado = $consultar(
            $empresa, $servico, $dados['documento'], $dados['finalidade'], $dados['solicitante'] ?? null,
        );

        if ($resultado['erro']) {
            return back()->withInput()->with('erro', $resultado['erro']);
        }

        return redirect()
            ->route('empresa.consultas.ver', $resultado['consulta'])
            ->with('ok', 'Consulta concluída.');
    }

    /** O resultado de uma consulta, so para quem a pediu. */
    public function verConsulta(Consulta $consulta)
    {
        abort_unless($consulta->cliente_id === auth('empresa')->id(), 403);

        return view('paginas.empresa.consulta', ['consulta' => $consulta->load('servico')]);
    }

    public function aceitar(DocumentoLegal $documento, RegistrarAceiteDocumento $registrar)
    {
        $registrar(auth('empresa')->user(), $documento);

        return back()->with('ok', 'Aceite registrado.');
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
        $aceitos = $empresa->aceitesDocumentos()->pluck('documento_id')->all();

        return DocumentoLegal::query()
            ->where('ativo', true)
            ->where('exige_aceite', true)
            ->whereNotIn('id', $aceitos)
            ->orderBy('titulo')
            ->get();
    }
}
