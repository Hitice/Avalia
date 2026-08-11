<?php

namespace App\Http\Controllers;

use App\Actions\Catalogo\GravarServicoCompleto;
use App\Http\Requests\ServicoRequest;
use App\Models\Catalogo;
use App\Models\Servico;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro dos servicos vendidos.
 *
 * Nao existe exclusao, so desativacao. Franquia de plano, e adiante consulta e
 * fatura, apontam para o servico: apagar levaria a franquia junto por cascata e
 * deixaria historico orfao. Servico que a Avalia parou de vender fica inativo,
 * some das telas de venda e continua explicando o passado.
 */
class ServicoController extends Controller
{
    public function index()
    {
        $servicos = Servico::query()
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get();

        return view('paginas.catalogo.servicos', [
            'servicos' => $servicos,
            // Servico sem produto do fornecedor nao consulta, e o erro so
            // aparece quando alguem tenta: aqui a pendencia fica visivel antes.
            'pendentes' => \App\Actions\Catalogo\SugerirProdutosBoaVista::pendentes(),
        ]);
    }

    public function criar()
    {
        return view('paginas.catalogo.servico-formulario', [
            'servico' => new Servico(['categoria' => 'credito', 'ativo' => true]),
            'catalogo' => Catalogo::vigente(),
            'faixas' => Catalogo::vigente()?->faixas() ?? [],
            'precos' => collect(),
        ]);
    }

    /**
     * Cria o servico ja precificado em todas as faixas.
     *
     * Servico sem preco nao aparece na matriz do catalogo, e a matriz e o unico
     * lugar onde se edita preco, e nasceria invisivel e inalcancavel. Por isso o
     * preco inicial e obrigatorio: entra igual em todas as faixas e a
     * administracao ajusta coluna a coluna depois.
     */
    public function salvar(ServicoRequest $request)
    {
        $catalogo = Catalogo::vigente();
        $faixas = $catalogo?->faixas() ?? [];

        if ($faixas === []) {
            return back()->withInput()->with('erro', 'Não há catálogo com faixas cadastradas.');
        }

        $dados = $request->validated();
        $precoBase = $dados['preco_base_cents'];
        unset($dados['preco_base_cents']);

        $servico = DB::transaction(function () use ($dados, $catalogo, $faixas, $precoBase) {
            $servico = Servico::create($dados);

            $catalogo->precos()->createMany(array_map(fn (int $faixa) => [
                'servico_id' => $servico->id,
                'consumo_minimo_cents' => $faixa,
                'preco_cents' => $precoBase,
            ], $faixas));

            return $servico;
        });

        return redirect()
            ->route('catalogo.servicos.index')
            ->with('ok', sprintf(
                "Serviço '%s' criado com preço inicial em %d faixa(s). Ajuste no catálogo.",
                $servico->nome,
                count($faixas),
            ));
    }

    /**
     * Uma pagina com tudo do servico: cadastro, custo e preco de cada faixa.
     *
     * Editar linha a linha em vez de uma matriz de 301 campos. O operador ve a
     * margem do que digitou antes de salvar.
     */
    public function editar(Servico $servico)
    {
        $catalogo = Catalogo::vigente();

        return view('paginas.catalogo.servico-formulario', [
            'servico' => $servico,
            'catalogo' => $catalogo,
            'faixas' => $catalogo?->faixas() ?? [],
            'precos' => $servico->precos()
                ->where('catalogo_id', $catalogo?->id)
                ->get()
                ->keyBy('consumo_minimo_cents'),
        ]);
    }

    public function atualizar(ServicoRequest $request, Servico $servico, GravarServicoCompleto $gravar)
    {
        // O codigo nao esta em validated() na edicao, entao nao ha como trocar
        // por payload forjado.
        $resultado = $gravar(
            Catalogo::vigente(),
            $servico,
            $request->validated(),
            $request->input('custo'),
            $request->input('precos', []),
        );

        if ($resultado['piso'] !== null) {
            return back()->withInput()->with('erro', sprintf(
                'Preço abaixo do piso e nada foi gravado. O menor valor que paga fornecedor, imposto e comissão é %s.',
                Dinheiro::brl($resultado['piso']),
            ));
        }

        return redirect()
            ->route('catalogo.servicos.index')
            ->with('ok', "Serviço '{$servico->nome}' atualizado.");
    }

    /**
     * Preenche o produto da Boa Vista nos servicos que ainda nao tem.
     *
     * Sugestao pelo nome comercial, e nao verdade contratual: produto que a
     * Avalia nao contratou volta recusado, e produto trocado devolve o
     * relatorio errado. Por isso a mensagem manda conferir, e a acao so toca no
     * que esta vazio.
     */
    public function sugerirProdutos(\App\Actions\Catalogo\SugerirProdutosBoaVista $sugerir)
    {
        $preenchidos = $sugerir();

        if ($preenchidos === 0) {
            return back()->with('erro', 'Nenhum serviço estava sem produto do fornecedor.');
        }

        return back()->with('ok', sprintf(
            '%d %s com o produto sugerido da Boa Vista. Confira contra o seu contrato antes de consultar: '
            .'produto não contratado volta recusado, e produto trocado devolve o relatório errado.',
            $preenchidos,
            $preenchidos === 1 ? 'serviço preenchido' : 'serviços preenchidos',
        ));
    }

    /** Liga e desliga o servico no clique, sem abrir formulario. */
    public function alternar(Servico $servico)
    {
        $servico->update(['ativo' => ! $servico->ativo]);

        return back()->with('ok', sprintf(
            "Serviço '%s' %s.",
            $servico->nome,
            $servico->ativo ? 'ativado' : 'pausado',
        ));
    }
}
