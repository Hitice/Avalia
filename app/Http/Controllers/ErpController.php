<?php

namespace App\Http\Controllers;

use App\Contabil\LivroCaixa;
use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Models\Staff;

/** A home do back office: o que os socios olham ao abrir, lido do razao. */
class ErpController extends Controller
{
    /** Roda os lastros e o relancamento. Idempotentes: repetir nao duplica. */
    public function conciliar()
    {
        $saida = '';

        foreach (['avalia:lastrear-plaquinhas', 'avalia:relancar-plaquinhas', 'avalia:lastrear-faturas', 'avalia:lastrear-parcelas', 'avalia:lastrear-contatos'] as $comando) {
            \Illuminate\Support\Facades\Artisan::call($comando);
            $saida .= $comando.': '.trim(preg_replace('/\s+/', ' ', (string) \Illuminate\Support\Facades\Artisan::output())).' · ';
        }

        return back()->with('ok', rtrim($saida, ' ·'));
    }

    public function apagarAportes()
    {
        \Illuminate\Support\Facades\Artisan::call('avalia:apagar-aportes');

        return back()->with('ok', trim(preg_replace('/\s+/', ' ', (string) \Illuminate\Support\Facades\Artisan::output())));
    }

    public function inicio()
    {
        $competencia = now()->format('Y-m');
        $doMes = LancamentoFinanceiro::daCompetencia($competencia)->with('partidas.conta')->get();
        $caixa = LivroCaixa::doMes($doMes, $competencia);

        return view('paginas.erp.inicio', [
            'competencia' => $competencia,
            'saldo' => LivroCaixa::saldoAtual(),
            'entradas' => $caixa['entradas'],
            'saidas' => $caixa['saidas'],
            'placasAPagar' => Etiqueta::comissaoEmAberto()->whereNotNull('vendedor_id')->count(),
            'equipe' => Staff::where('ativo', true)->count(),
        ]);
    }
}
