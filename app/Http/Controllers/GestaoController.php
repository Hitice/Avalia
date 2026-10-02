<?php

namespace App\Http\Controllers;

use App\Contabil\LivroCaixa;
use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Models\Staff;

/** A home do back office: o que os socios olham ao abrir, lido do razao. */
class GestaoController extends Controller
{
    public function inicio()
    {
        $competencia = now()->format('Y-m');
        $doMes = LancamentoFinanceiro::daCompetencia($competencia)->with('partidas.conta')->get();
        $caixa = LivroCaixa::doMes($doMes, $competencia);

        return view('paginas.gestao.inicio', [
            'competencia' => $competencia,
            'saldo' => LivroCaixa::saldoAtual(),
            'entradas' => $caixa['entradas'],
            'saidas' => $caixa['saidas'],
            'placasAPagar' => Etiqueta::comissaoEmAberto()->whereNotNull('vendedor_id')->count(),
            'equipe' => Staff::where('ativo', true)->count(),
        ]);
    }
}
