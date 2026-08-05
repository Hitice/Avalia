<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\Staff;
use App\Support\Auditar;

/**
 * Quem trabalha na Avalia: administracao e vendedores.
 *
 * E aqui que se define o percentual de comissao de cada vendedor. A taxa vale
 * do proximo fechamento em diante, porque a fatura congela o percentual usado
 * na emissao (PDD.md, secao 9).
 *
 * Nao ha exclusao, so desativacao: fatura, carteira e trilha de auditoria
 * apontam para o membro, e apagar deixaria historico orfao.
 */
class EquipeController extends Controller
{
    public function index()
    {
        return view('paginas.equipe.index', [
            'membros' => Staff::withCount('clientes')->orderBy('nome')->get(),
        ]);
    }

    public function criar()
    {
        return view('paginas.equipe.formulario', [
            'membro' => new Staff(['papel' => 'vendedor', 'ativo' => true, 'comissao_pct' => 10]),
        ]);
    }

    public function salvar(StaffRequest $request)
    {
        $membro = Staff::create($request->dados());

        Auditar::registrar('equipe.criada', $membro, [
            'papel' => $membro->papel,
            'comissao_pct' => $membro->comissao_pct,
        ]);

        return redirect()
            ->route('equipe.index')
            ->with('ok', "{$membro->nome} cadastrado.");
    }

    public function editar(Staff $membro)
    {
        return view('paginas.equipe.formulario', ['membro' => $membro]);
    }

    public function atualizar(StaffRequest $request, Staff $membro)
    {
        $antes = ['papel' => $membro->papel, 'comissao_pct' => $membro->comissao_pct];

        $membro->update($request->dados());

        // Trocar papel ou desativar muda o que a pessoa alcanca. A sessao aberta
        // continuaria com a permissao antiga ate o cookie expirar.
        if (! $membro->podeEntrar() || $antes['papel'] !== $membro->papel) {
            $membro->revogaSessoes();
        }

        if ($antes !== ['papel' => $membro->papel, 'comissao_pct' => $membro->comissao_pct]) {
            Auditar::registrar('equipe.alterada', $membro, [
                'papel' => $membro->papel,
                'comissao_pct' => $membro->comissao_pct,
            ]);
        }

        return redirect()
            ->route('equipe.index')
            ->with('ok', "Cadastro de {$membro->nome} atualizado.");
    }
}
