<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Lead;
use App\Models\Plano;
use App\Models\Preco;
use App\Models\Servico;
use App\Support\Comissao;
use App\Support\Dinheiro;
use App\Support\FiltroConsultas;
use App\Support\FiltroLeads;
use App\Support\Simulacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A carteira do vendedor: as empresas dele, o consumo delas e o que ele tem a
 * receber, mais as duas ferramentas que ele usa para vender.
 *
 * Telas separadas das de administracao de proposito, e nao um filtro aplicado
 * naquelas. As telas de admin mostram custo do fornecedor, lucro e margem, que
 * sao internos e nunca vao para o vendedor (PDD.md, secao 6). Reaproveitar a
 * mesma tela com condicionais deixaria cada campo novo a um `@if` de distancia
 * de vazar.
 *
 * O vendedor so enxerga o proprio `staff_id`: nao existe parametro de rota que
 * escolha a carteira, entao nao ha como pedir a de outro trocando a URL.
 */
class CarteiraController extends Controller
{
    private const POR_PAGINA = 25;

    public function index()
    {
        $vendedor = Auth::guard('staff')->user();

        $empresas = Cliente::query()
            ->with('plano')
            ->where('vendedor_id', $vendedor->id)
            ->orderBy('razao_social')
            ->get();

        $faturas = Fatura::query()
            ->with('cliente')
            ->where('vendedor_id', $vendedor->id)
            ->orderByDesc('competencia')
            ->get();

        $competencia = Consulta::competenciaDe();

        $demonstracoes = (int) Consulta::query()
            ->where('vendedor_id', $vendedor->id)
            ->where('situacao', Consulta::SUCESSO)
            ->sum('custo_cents');

        return view('paginas.carteira.index', [
            'vendedor' => $vendedor,
            'empresas' => $empresas,
            'faturas' => $faturas,
            'competencia' => $competencia,
            // Consumo do mes por empresa, que e sobre o que a comissao vai
            // incidir quando a competencia fechar.
            'consumo' => Consulta::query()
                ->whereIn('cliente_id', $empresas->pluck('id'))
                ->where('competencia', $competencia)
                ->selectRaw('cliente_id, sum(preco_cents) as total')
                ->groupBy('cliente_id')
                ->pluck('total', 'cliente_id'),
            'aReceber' => max(0, (int) $faturas->whereNotNull('comissao_liberada_em')->sum('comissao_cents') - $demonstracoes),
            'aConfirmar' => (int) $faturas->whereNull('comissao_liberada_em')->sum('comissao_cents'),
            // O custo das demonstracoes sai da comissao (regra do negocio); a
            // propria consulta e o registro do desconto.
            'demonstracoes' => $demonstracoes,
        ]);
    }

    /**
     * As consultas das empresas da carteira.
     *
     * Serve para o vendedor acompanhar quem esta usando e quem parou de usar,
     * que e o sinal mais antecipado de cancelamento que existe na operacao.
     *
     * Mostra o valor que a empresa pagou, que e o preco de tabela que ele mesmo
     * vendeu. Nao mostra custo do fornecedor.
     */
    public function consultas(Request $pedido)
    {
        $vendedor = Auth::guard('staff')->user();

        // O recorte vem do vinculo da empresa com o vendedor, nao da URL: sem
        // esta subconsulta, filtro nenhum impediria ver a carteira alheia.
        $daCarteira = Consulta::query()->where(fn ($q) => $q
            ->whereIn('cliente_id', Cliente::query()->where('vendedor_id', $vendedor->id)->select('id'))
            // As demonstracoes dele entram na mesma lista: sao consultas da
            // carteira tanto quanto as dos clientes.
            ->orWhere('vendedor_id', $vendedor->id));

        $filtradas = FiltroConsultas::aplicar($daCarteira, $pedido);

        return view('paginas.carteira.consultas', [
            'vendedor' => $vendedor,
            'escolha' => FiltroConsultas::escolhido($pedido),
            'resumo' => FiltroConsultas::resumo($filtradas),
            'servicos' => Servico::orderBy('nome')->get(),
            'consultas' => $filtradas->with(['servico', 'cliente'])->latest('id')
                ->paginate(self::POR_PAGINA)->withQueryString(),
        ]);
    }

    /**
     * Os leads que a administracao passou para ele.
     *
     * O recorte vem do vinculo, e nao da URL: o vendedor nao alcanca a base de
     * prospeccao, so o pedaco que foi compartilhado com ele. Nao ha parametro de
     * rota que escolha a lista de outro, e o filtro roda dentro do vinculo.
     *
     * Sem custo, sem margem, sem numero interno: e uma lista de quem ligar.
     */
    public function leads(Request $pedido)
    {
        $vendedor = Auth::guard('staff')->user();

        $dele = Lead::query()->whereHas(
            'vendedores',
            fn (Builder $q) => $q->where('staff.id', $vendedor->id)
        );

        $filtrados = FiltroLeads::aplicar($dele, $pedido);

        return view('paginas.carteira.leads', [
            'vendedor' => $vendedor,
            'escolha' => FiltroLeads::escolhido($pedido),
            // Carrega so o proprio vinculo: a lista mostra "com voce desde", e
            // trazer os outros vendedores seria dizer a ele com quem mais o lead
            // esta, que e informacao da administracao.
            'leads' => (clone $filtrados)
                ->with(['vendedores' => fn (BelongsToMany $q) => $q->where('staff.id', $vendedor->id)])
                ->orderBy('nome')->paginate(self::POR_PAGINA)->withQueryString(),
            'total' => (clone $filtrados)->count(),
            'comTelefone' => (clone $filtrados)->whereNotNull('telefone')->count(),
            'comEmail' => (clone $filtrados)->whereNotNull('email')->count(),

            // As opcoes dos filtros saem da propria lista dele: oferecer UF que
            // ele nao tem lead nenhum e oferecer uma tela vazia.
            'ufs' => (clone $dele)->whereNotNull('uf')->distinct()->orderBy('uf')->pluck('uf'),
            'cidades' => (clone $dele)->whereNotNull('cidade')->distinct()->orderBy('cidade')->pluck('cidade'),
            'origens' => (clone $dele)->whereNotNull('origem')->distinct()->pluck('origem')
                ->sortBy(fn (string $origem) => (int) $origem)->values(),
        ]);
    }

    /**
     * O que ele pode vender, por faixa, com o preco que o cliente paga.
     *
     * Substitui a captura de tela da planilha que circulava por mensagem: aqui o
     * preco e sempre o do catalogo vigente, entao ninguem vende pelo valor do
     * reajuste passado.
     */
    public function servicos()
    {
        $vendedor = Auth::guard('staff')->user();

        // Todas as faixas lado a lado, e nao uma por vez: comparar "quanto
        // custa a consulta 7 no plano de 200 e no de 900" era abrir a tela
        // duas vezes e anotar. A coluna e o plano, a linha e o servico.
        $planos = Plano::query()->where('ativo', true)->orderBy('consumo_minimo_cents')->get();

        $servicos = \App\Models\Servico::query()
            ->disponiveis()
            ->orderBy('numero')
            ->get();

        // O `select` e explicito e nao traz `custo_cents`: a coluna existe na
        // mesma linha do preco, e um dia alguem faz um `@foreach` sobre os
        // atributos. Nao carregar e mais firme do que lembrar de nao imprimir.
        $precos = Preco::query()
            ->select('servico_id', 'catalogo_id', 'consumo_minimo_cents', 'preco_cents')
            ->whereIn('servico_id', $servicos->pluck('id'))
            ->get()
            ->keyBy(fn ($p) => $p->catalogo_id.':'.$p->consumo_minimo_cents.':'.$p->servico_id);

        return view('paginas.carteira.servicos', [
            'vendedor' => $vendedor,
            'planos' => $planos,
            'servicos' => $servicos,
            'precos' => $precos,
        ]);
    }

    /**
     * A ficha de uma empresa da carteira, na visao do vendedor.
     *
     * Tudo que ele precisa para a conversa com o cliente: contato, plano,
     * consumo do mes e faturas pelo preco de venda. Custo, lucro e margem nao
     * existem aqui; a ficha completa com numeros internos e a da administracao.
     */
    public function empresa(Cliente $empresa)
    {
        abort_unless($empresa->vendedor_id === Auth::guard('staff')->id(), 403);

        $competencia = Consulta::competenciaDe();

        return view('paginas.carteira.empresa', [
            'empresa' => $empresa->load('plano'),
            'competencia' => $competencia,
            'consumo' => (int) $empresa->consultas()
                ->where('competencia', $competencia)
                ->where('situacao', Consulta::SUCESSO)
                ->sum('preco_cents'),
            'quantidade' => $empresa->consultas()->where('competencia', $competencia)->count(),
            'faturas' => $empresa->faturas()->orderByDesc('competencia')->limit(12)->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Demonstracao
    |--------------------------------------------------------------------------
    |
    | A consulta que a casa faz sem cliente do outro lado. Para o vendedor, e a
    | demonstracao que fecha venda: preco zero e custo descontado da comissao
    | dele. Para a administracao, e consulta a trabalho: preco zero e custo da
    | operacao, sem comissao de onde descontar. Teto diario proprio nos dois.
    |
    */

    public function consultar()
    {
        $vendedor = Auth::guard('staff')->user();

        // O preco de TABELA (faixa sem minimo), para o card dizer quanto a
        // consulta vale no catalogo. Preco de venda o vendedor pode ver; custo
        // e margem continuam fora desta tela.
        $precos = \App\Models\Catalogo::vigente()
            ?->precos()->where('consumo_minimo_cents', 0)->pluck('preco_cents', 'servico_id') ?? collect();

        $estrelas = \App\Support\Estrelas::porPreco($precos);

        return view('paginas.carteira.consultar', [
            'vendedor' => $vendedor,
            'servicos' => Servico::query()->disponiveis()->orderBy('numero')->get(),
            'precos' => $precos,
            'estrelas' => $estrelas,
            'restantes' => \App\Actions\Consumo\ExecutarDemonstracao::restantes($vendedor),
        ]);
    }

    public function executarDemonstracao(Request $pedido, \App\Actions\Consumo\ExecutarDemonstracao $demonstrar)
    {
        $dados = $pedido->validate([
            'servico_id' => ['required', 'integer'],
            'documento' => ['required', 'string', 'min:11', 'max:20'],
        ], [
            'documento.min' => 'Informe um CPF ou CNPJ completo.',
        ]);

        $resultado = $demonstrar(
            Auth::guard('staff')->user(),
            Servico::findOrFail($dados['servico_id']),
            $dados['documento'],
        );

        if ($resultado['erro']) {
            return back()->withInput()->with('erro', $resultado['erro']);
        }

        // De volta a PROPRIA tela de consultar, com o visor do laudo aberto
        // por cima: a proxima consulta comeca onde a anterior terminou, sem
        // pagina intermediaria no caminho.
        return redirect()->route('carteira.consultar', ['laudo' => $resultado['consulta']->id]);
    }

    /** O resultado, so para o vendedor que consultou. */
    public function verDemonstracao(Consulta $consulta)
    {
        abort_unless($consulta->vendedor_id === Auth::guard('staff')->id(), 403);

        return view('paginas.carteira.consulta', ['consulta' => $consulta->load('servico')]);
    }

    /** O PDF compartilhavel: arquivo em mao, nunca dado pessoal em URL. */
    public function demonstracaoPdf(Consulta $consulta)
    {
        abort_unless($consulta->vendedor_id === Auth::guard('staff')->id(), 403);
        abort_unless($consulta->deuCerto() && ! $consulta->expurgada(), 404);

        return response(\App\Support\ConsultaPdf::resultado($consulta, Auth::guard('staff')->user()->nome), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="consulta-'.($consulta->referencia_externa ?? $consulta->id).'.pdf"',
        ]);
    }

    /**
     * Simulador de proposta do vendedor.
     *
     * Responde a pergunta que o cliente faz na mesa: quanto vou pagar por mes se
     * consumir isto? E a pergunta que o vendedor faz em seguida: quanto sobra
     * para mim?
     *
     * Custo do fornecedor, imposto, lucro e margem nao aparecem: sao numeros
     * internos, e a tela do vendedor e uma das que o cliente olha por cima do
     * ombro. A comissao aparece porque sem ela a ferramenta nao serve para
     * decidir desconto, que e para o que ela existe.
     *
     * Tudo por GET e nada e gravado: simulacao nao e proposta. O endereco carrega
     * o cenario inteiro, entao o vendedor manda o link em vez de a captura.
     */
    public function simulacao(Request $pedido)
    {
        $vendedor = Auth::guard('staff')->user();

        $catalogo = Catalogo::vigente();
        $faixas = $catalogo?->faixas() ?? [];

        $faixa = $this->faixaEscolhida($pedido, $faixas);
        $plano = Plano::where('consumo_minimo_cents', $faixa)->where('ativo', true)->first();

        $mensalidade = Dinheiro::paraCentavos($pedido->query('mensalidade')) ?? $plano?->mensalidade_cents ?? 0;

        // Sem consumo informado, o cenario neutro e o cliente que consome
        // exatamente o minimo: e o que a proposta promete.
        $consumo = Dinheiro::paraCentavos($pedido->query('consumo')) ?? $faixa;

        $adesao = Dinheiro::paraCentavos($pedido->query('adesao')) ?? 0;
        $parcelas = max(1, (int) $pedido->query('parcelas', 1));

        // O minimo negociado pode ser livre (plano flexivel): a faixa escolhe
        // a tabela de precos e o custo, o minimo so o piso de cobranca.
        $minimo = Dinheiro::paraCentavos($pedido->query('minimo')) ?? $faixa;

        $mes = Simulacao::mensal(
            consumoCents: $consumo,
            consumoMinimoCents: $minimo,
            mensalidadeCents: $mensalidade,
            custoSobreVendaBps: $catalogo?->custoSobreVendaBps($faixa) ?? 0,
            impostoBps: $catalogo?->imposto_bps ?? 0,
            comissaoPct: $vendedor->comissao_pct,
        );

        return view('paginas.carteira.simulacao', [
            'vendedor' => $vendedor,
            'faixas' => $faixas,
            'faixa' => $faixa,
            'plano' => $plano,
            // So o que o vendedor pode ver. O array de Simulacao traz custo,
            // imposto e lucro juntos, e mandar ele inteiro para a view deixaria
            // os tres a um `{{ }}` de distancia da tela.
            'proposta' => [
                'fatura_cents' => $mes['fatura_cents'],
                'mensalidade_cents' => $mensalidade,
                'consumo_faturado_cents' => $mes['consumo_faturado_cents'],
                'pagou_sem_usar_cents' => $mes['pagou_sem_usar_cents'],
                'comissao_cents' => $mes['comissao_cents'],
            ],
            'adesaoDoVendedor' => Simulacao::adesaoDoMes($adesao, $parcelas)['vendedor_cents'],
            'pctComissao' => Comissao::pct($vendedor->comissao_pct ?? null),
            'entrada' => [
                'minimo' => $minimo,
                'consumo' => $consumo,
                'mensalidade' => $mensalidade,
                'adesao' => $adesao,
                'parcelas' => $parcelas,
            ],
        ]);
    }

    /** @param  list<int>  $faixas */
    private function faixaEscolhida(Request $pedido, array $faixas): int
    {
        $pedida = Dinheiro::paraCentavos($pedido->query('faixa'));

        if ($pedida !== null && in_array($pedida, $faixas, true)) {
            return $pedida;
        }

        // Faixa do meio como padrao: comeca num cenario plausivel em vez do
        // extremo, que sempre parece bom demais ou ruim demais.
        return $faixas === [] ? 0 : $faixas[intdiv(count($faixas), 2)];
    }
}
