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
    /** Quem conta para a meta: vendedores ativos com acesso ao Sales, e os socios. */
    public static function pessoasQueVendem(array $socios): int
    {
        return \App\Models\Staff::where('ativo', true)
            ->where(fn ($q) => $q->where(fn ($v) => $v->where('papel', 'vendedor')->where('acessa_sales', true))->orWhereIn('id', $socios))
            ->count();
    }

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

        $minhas = $soma(Etiqueta::where('vendedor_id', $conta->id)->whereBetween('vendida_em', [$inicio, $fim])->get());
        $posicao = array_search((int) $conta->id, $socios, true);
        $emAberto = $soma(Etiqueta::where('vendedor_id', $conta->id)->comissaoEmAberto()->get());
        $daEquipe = $ehAdmin || $posicao !== false
            ? Etiqueta::whereNotNull('vendida_em')->whereBetween('vendida_em', [$inicio, $fim])->with('vendedor:id,nome')->get()
            : null;
        $equipe = $daEquipe === null ? null : $soma($daEquipe);

        // A operacao inteira, vendedor a vendedor, socios inclusive: e o que a
        // administracao abre a tela para ver.
        $porVendedor = $daEquipe === null ? collect() : $daEquipe
            ->groupBy(fn (Etiqueta $e) => $e->vendedor_id ?? 0)
            ->map(fn ($vendas, $id) => [
                'nome' => $id === 0 ? 'Sem vendedor' : ($vendas->first()->vendedor?->nome ?? 'Conta removida'),
                'socio' => in_array((int) $id, $socios, true),
                'placas' => $vendas->count(),
                'bruto' => (int) $vendas->sum('valor_cents'),
            ])
            ->sortByDesc('placas')->values();

        // Socio nao tem comissao: tem a parte dele no lucro de todas as vendas
        // do mes, inclusive as dos vendedores, menos o que fica na empresa.
        $minhaParte = null;

        if ($posicao !== false) {
            $parte = RepartePlaquinha::dividir($equipe['lucro'], count($socios))[$posicao];
            $minhaParte = ['parte' => $parte] + RepartePlaquinha::retencao($parte, (int) config('etiquetas.retencao_pct'));
        }

        return view('paginas.sales.inicio', [
            'ehAdmin' => $ehAdmin,
            'emMaosMinhas' => Etiqueta::noEstoqueDe((int) $conta->id)->count(),
            'minhas' => $minhas,
            'equipe' => $equipe,
            'porVendedor' => $porVendedor,
            'minhaParte' => $minhaParte,
            'comissaoAtual' => $emAberto['comissao'],
            'mes' => $inicio,

            // Vendedor ve as proprias vendas; socio e admin, as da equipe.
            'porDia' => Etiqueta::vendasPorDia($inicio, $equipe === null ? (int) $conta->id : null),
            'meta' => $equipe === null
                ? \App\Support\MetaDePlacas::doMes($inicio, 1, $minhas['placas'], (int) config('etiquetas.meta_por_pessoa'))
                : \App\Support\MetaDePlacas::doMes($inicio, self::pessoasQueVendem($socios), $equipe['placas'], (int) config('etiquetas.meta_por_pessoa')),
            'noBolo' => $ehAdmin ? Etiqueta::semDono()->count() : null,
            'disponiveis' => $ehAdmin ? Etiqueta::whereNull('vendida_em')->count() : null,
            'aPagar' => $ehAdmin ? \App\Actions\Financeiro\Repasses::comissoesSales() : collect(),
            // Quem esta com placa na mao, para a administracao ver de relance.
            'emMaos' => $ehAdmin ? Etiqueta::whereNull('vendida_em')->whereNotNull('consignada_para_id')
                ->selectRaw('consignada_para_id, count(*) as total')->groupBy('consignada_para_id')->with('consignadaPara:id,nome')->get() : collect(),
        ]);
    }
}
