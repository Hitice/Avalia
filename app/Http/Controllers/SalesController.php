<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\VendaNoRazao;
use App\Models\Etiqueta;
use App\Support\RepartePlaquinha;
use App\Support\SociosDaPlaquinha;
use Illuminate\Http\Request;

/**
 * A home do Avalia Sales, para a equipe.
 *
 * Nao e o painel de vendas nem o estoque: e o que a pessoa precisa saber ao
 * entrar, com o caminho para cada tela. O cliente e o produtor nao passam por
 * aqui; eles entram pelo QR dinamico e veem o proprio codigo.
 */
class SalesController extends Controller
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
        $posicao = array_search((int) $conta->id, $socios, true);
        $emAberto = $soma(Etiqueta::where('vendedor_id', $conta->id)->comissaoEmAberto()->get());
        $equipe = $ehAdmin || $posicao !== false
            ? $soma(Etiqueta::whereNotNull('vendida_em')->whereBetween('vendida_em', [$inicio, $fim])->get())
            : null;

        // Socio nao tem comissao: tem a parte dele no lucro de todas as vendas
        // do mes, inclusive as dos vendedores, e metade dela fica na empresa.
        $minhaParte = null;

        if ($posicao !== false) {
            $parte = RepartePlaquinha::dividir($equipe['lucro'], count($socios))[$posicao];
            $minhaParte = ['parte' => $parte] + RepartePlaquinha::retencao($parte, (int) config('etiquetas.retencao_pct'));
        }

        return view('paginas.sales.inicio', [
            'ehAdmin' => $ehAdmin,
            'emMaos' => Etiqueta::noEstoqueDe((int) $conta->id)->count(),
            'minhas' => $soma($minhas),
            'equipe' => $equipe,
            'minhaParte' => $minhaParte,
            'comissaoAtual' => $emAberto['comissao'],
            'mes' => $inicio,

            // Vendedor ve as proprias vendas; socio e admin, as da equipe.
            'porDia' => Etiqueta::vendasPorDia($inicio, $equipe === null ? (int) $conta->id : null),
            'noBolo' => $ehAdmin ? Etiqueta::semDono()->count() : null,
        ]);
    }
}
