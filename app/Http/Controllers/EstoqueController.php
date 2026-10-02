<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\ConsignarPlacas;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Http\Request;

/**
 * O estoque pessoal de placas.
 *
 * O vendedor ve o que esta na mao DELE, e e por isso que a tela existe: ele
 * precisa saber quantas ainda tem antes de prometer entrega. A administracao ve
 * de todos e entrega lotes.
 */
class EstoqueController extends Controller
{
    public function index(Request $pedido)
    {
        $conta = $pedido->user('staff');
        $ehAdmin = (bool) ($conta?->ehAdmin() || $conta?->ehSuper());

        return view('paginas.salles.estoque', [
            'ehAdmin' => $ehAdmin,
            'minhas' => Etiqueta::noEstoqueDe((int) $conta->id)->orderBy('sequencia')->get(),

            // So a administracao ve a conta dos outros: quanto cada um tem na mao
            // e quanto ja vendeu daquilo.
            'porVendedor' => $ehAdmin ? $this->porVendedor() : collect(),
            'equipe' => $ehAdmin ? Staff::orderBy('nome')->get(['id', 'nome']) : collect(),
            'noBolo' => $ehAdmin ? Etiqueta::semDono()->count() : null,
        ]);
    }

    public function entregar(Request $pedido, ConsignarPlacas $consignar)
    {
        $dados = $pedido->validate([
            'vendedor_id' => ['required', 'integer', 'exists:staff,id'],
            'quantas' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $vendedor = Staff::findOrFail($dados['vendedor_id']);

        try {
            $quantas = $consignar($vendedor, (int) $dados['quantas']);
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage());
        }

        return back()->with('ok', $quantas.' placas entregues a '.$vendedor->nome.'.');
    }

    public function devolver(Request $pedido, ConsignarPlacas $consignar, Staff $vendedor)
    {
        try {
            $quantas = $consignar->devolver($vendedor);
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage());
        }

        return back()->with('ok', $quantas.' placas de '.$vendedor->nome.' voltaram ao estoque da casa.');
    }

    /** Quem tem o que na mao, e quanto ja vendeu. */
    private function porVendedor()
    {
        return Staff::query()
            ->withCount([
                'placasConsignadas as em_maos' => fn ($q) => $q->whereNull('vendida_em'),
                'vendasDePlaquinha as vendidas',
            ])
            ->orderByDesc('em_maos')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Staff $s) => $s->em_maos > 0 || $s->vendidas > 0)
            ->values();
    }
}
