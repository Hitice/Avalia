<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\VendaNoRazao;
use App\Models\Etiqueta;
use App\Support\SociosDaPlaquinha;
use Illuminate\Http\Request;

/**
 * A home do Avalia Salles, para a equipe.
 *
 * Nao e o painel de vendas nem o estoque: e o que a pessoa precisa saber ao
 * entrar, com o caminho para cada tela. O cliente e o produtor nao passam por
 * aqui; eles entram pelo QR dinamico e veem o proprio codigo.
 */
class SallesController extends Controller
{
    public function inicio(Request $pedido)
    {
        $conta = $pedido->user('staff');
        $ehAdmin = (bool) ($conta?->ehAdmin() || $conta?->ehSuper());
        $inicio = now()->startOfMonth();
        $fim = now()->endOfMonth();
        $socios = SociosDaPlaquinha::resolver()['ids'];

        $soma = function ($vendas) use ($socios) {
            $t = ['placas' => 0, 'bruto' => 0, 'comissao' => 0, 'lucro' => 0];

            foreach ($vendas as $venda) {
                $parte = VendaNoRazao::reparte($venda, $socios);
                $t['placas']++;
                $t['bruto'] += $parte['bruto'];
                $t['comissao'] += $parte['comissao'];
                $t['lucro'] += $parte['lucro'];
            }

            return $t;
        };

        $minhas = Etiqueta::where('vendedor_id', $conta->id)->whereBetween('vendida_em', [$inicio, $fim])->get();

        return view('paginas.salles.inicio', [
            'ehAdmin' => $ehAdmin,
            'emMaos' => Etiqueta::noEstoqueDe((int) $conta->id)->count(),
            'minhas' => $soma($minhas),
            'equipe' => $ehAdmin
                ? $soma(Etiqueta::whereNotNull('vendida_em')->whereBetween('vendida_em', [$inicio, $fim])->get())
                : null,
            'noBolo' => $ehAdmin ? Etiqueta::semDono()->count() : null,
        ]);
    }
}
