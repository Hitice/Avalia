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

        return view('paginas.sales.estoque', [
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

    /** Entrega pelos codigos digitados, separados por virgula. Tudo ou nada. */
    public function entregarPorCodigos(Request $pedido, ConsignarPlacas $consignar)
    {
        $dados = $pedido->validate([
            'vendedor_id' => ['required', 'integer', 'exists:staff,id'],
            'codigos' => ['required', 'string', 'max:2000'],
        ]);

        $vendedor = Staff::findOrFail($dados['vendedor_id']);
        // Virgula ou linha separam; espaco dentro do codigo ("EF O GH3") e de quem
        // le da placa e some. Separar por espaco partia um codigo em tres.
        $lista = array_map(fn ($c) => preg_replace('/\s+/', '', $c),
            preg_split('/[,;\n]+/', $dados['codigos'], -1, PREG_SPLIT_NO_EMPTY));

        try {
            $entregues = $consignar->porCodigos($vendedor, $lista);
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage())->withInput();
        }

        return back()->with('ok', count($entregues).' placas entregues a '.$vendedor->nome.': '.implode(', ', $entregues).'.');
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
