<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Cliente;
use App\Models\Servico;
use App\Support\Auditar;
use Illuminate\Http\Request;

class CampanhaController extends Controller
{
    public function index()
    {
        return view('paginas.campanhas.index', ['campanhas' => Campanha::withCount(['clientes', 'servicos'])->orderByDesc('inicio')->get()]);
    }

    public function criar()
    {
        return view('paginas.campanhas.formulario', ['clientes' => Cliente::orderBy('razao_social')->get(), 'servicos' => Servico::where('ativo', true)->orderBy('nome')->get()]);
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'], 'oferta' => ['required', 'string'],
            'inicio' => ['required', 'date'], 'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
            'clientes' => ['array'], 'clientes.*' => ['integer', 'exists:clientes,id'],
            'servicos' => ['array'], 'servicos.*' => ['integer', 'exists:servicos,id'],
        ]);
        $campanha = Campanha::create([...$dados, 'ativa' => true]);
        $campanha->clientes()->sync($dados['clientes'] ?? []);
        $campanha->servicos()->sync($dados['servicos'] ?? []);
        Auditar::registrar('campanha.criada', $campanha, ['clientes' => count($dados['clientes'] ?? []), 'servicos' => count($dados['servicos'] ?? [])]);

        return redirect()->route('campanhas.index')->with('ok', 'Campanha criada.');
    }

    /**
     * Liga e desliga sem editar: a campanha vigente aparece na pagina publica,
     * entao encerrar precisa ser um clique, e nao um formulario.
     */
    public function alternar(Campanha $campanha)
    {
        $campanha->update(['ativa' => ! $campanha->ativa]);
        Auditar::registrar($campanha->ativa ? 'campanha.reaberta' : 'campanha.encerrada', $campanha);

        return back()->with('ok', $campanha->ativa ? 'Campanha reaberta.' : 'Campanha encerrada.');
    }
}
