<?php

namespace App\Http\Controllers;

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Http\Requests\EmpresaRequest;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Plano;
use App\Models\Servico;
use App\Models\Staff;
use App\Support\Dinheiro;
use Illuminate\Http\Request;

/**
 * As empresas contratantes e o consumo delas.
 *
 * A ficha da empresa e o lugar onde o fluxo inteiro se ve de uma vez: plano
 * contratado, consultas do mes em aberto e faturas fechadas. Enquanto nao ha
 * integracao com o fornecedor, a consulta e registrada a mao daqui, e e assim
 * que se valida a cadeia catalogo, consumo e fatura de ponta a ponta.
 */
class EmpresaController extends Controller
{
    public function index()
    {
        return view('paginas.empresas.index', [
            'empresas' => Cliente::with(['plano', 'vendedor'])->orderBy('razao_social')->get(),
        ]);
    }

    public function criar()
    {
        return view('paginas.empresas.formulario', $this->opcoes(new Cliente(['situacao' => 'ativo'])));
    }

    public function salvar(EmpresaRequest $request)
    {
        $empresa = Cliente::create($request->dados());

        return redirect()
            ->route('empresas.ficha', $empresa)
            ->with('ok', "Empresa '{$empresa->razao_social}' cadastrada.");
    }

    public function editar(Cliente $empresa)
    {
        return view('paginas.empresas.formulario', $this->opcoes($empresa));
    }

    public function atualizar(EmpresaRequest $request, Cliente $empresa)
    {
        $empresa->update($request->dados());

        // Situacao que fecha o acesso derruba a sessao aberta na hora, senao a
        // empresa continua consultando ate o cookie expirar.
        if (! $empresa->podeEntrar()) {
            $empresa->revogaSessoes();
        }

        return redirect()
            ->route('empresas.ficha', $empresa)
            ->with('ok', 'Cadastro atualizado.');
    }

    public function ficha(Cliente $empresa)
    {
        $competencia = Consulta::competenciaDe();

        return view('paginas.empresas.ficha', [
            'empresa' => $empresa->load(['plano.catalogo', 'vendedor']),
            'competencia' => $competencia,
            'consumo' => $empresa->consultas()->where('competencia', $competencia)->sum('preco_cents'),
            'quantidade' => $empresa->consultas()->where('competencia', $competencia)->count(),
            'servicos' => $empresa->plano?->servicosDisponiveis() ?? collect(),
            'faturas' => $empresa->faturas()->orderByDesc('competencia')->get(),
        ]);
    }

    public function consultar(Request $request, Cliente $empresa, RegistrarConsulta $registrar)
    {
        $servico = Servico::findOrFail($request->input('servico_id'));
        $resultado = $registrar($empresa, $servico, (int) $request->input('quantidade', 1));

        if ($resultado['erro']) {
            return back()->with('erro', $resultado['erro']);
        }

        return back()->with('ok', sprintf(
            '%d consulta(s) de %s registradas, %s.',
            $resultado['consultas'],
            $servico->nome,
            Dinheiro::brl($resultado['total_cents']),
        ));
    }

    public function fechar(Cliente $empresa, FecharCompetencia $fechar)
    {
        $resultado = $fechar($empresa, Consulta::competenciaDe());

        if ($resultado['erro']) {
            return back()->with('erro', $resultado['erro']);
        }

        return back()->with('ok', sprintf(
            'Competencia fechada: fatura de %s, vencimento em %s.',
            $resultado['fatura']->totalRotulo(),
            $resultado['fatura']->vencimento()->format('d/m/Y'),
        ));
    }

    /** @return array<string, mixed> */
    private function opcoes(Cliente $empresa): array
    {
        return [
            'empresa' => $empresa,
            'planos' => Plano::where('ativo', true)->orderBy('consumo_minimo_cents')->get(),
            'vendedores' => Staff::where('ativo', true)->orderBy('nome')->get(),
        ];
    }
}
