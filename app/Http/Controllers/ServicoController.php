<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServicoRequest;
use App\Models\Catalogo;
use App\Models\Servico;
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
            ->withCount('precos')
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get();

        return view('paginas.catalogo.servicos', ['servicos' => $servicos]);
    }

    public function criar()
    {
        return view('paginas.catalogo.servico-formulario', [
            'servico' => new Servico(['categoria' => 'credito', 'ativo' => true]),
            'faixas' => Catalogo::vigente()?->faixas() ?? [],
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
            return back()->withInput()->with('erro', 'Nao ha catalogo com faixas cadastradas.');
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
                "Servico '%s' criado com preco inicial em %d faixa(s). Ajuste no catalogo.",
                $servico->nome,
                count($faixas),
            ));
    }

    public function editar(Servico $servico)
    {
        return view('paginas.catalogo.servico-formulario', [
            'servico' => $servico,
            'faixas' => [],
        ]);
    }

    public function atualizar(ServicoRequest $request, Servico $servico)
    {
        // O codigo nao esta em validated() na edicao, entao nao ha como trocar
        // por payload forjado.
        $servico->update($request->validated());

        return redirect()
            ->route('catalogo.servicos.index')
            ->with('ok', "Servico '{$servico->nome}' atualizado.");
    }
}
