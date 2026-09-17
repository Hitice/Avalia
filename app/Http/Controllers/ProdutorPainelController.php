<?php

namespace App\Http\Controllers;

use App\Models\Lancamento360;
use App\Models\Parcela360;
use App\Models\Pedido360;
use Illuminate\Support\Facades\Auth;

/**
 * O painel do produtor: o que vendeu, o que tem a receber e o que atrasou.
 *
 * Os numeros saem do razao e das parcelas, e nao de coluna de saldo: e a mesma
 * regra do resto do 360, porque saldo guardado e a primeira coisa a divergir
 * do extrato.
 *
 * Produtor pendente ve o painel, e ve vazio com a explicacao do que falta.
 * Esconder a tela de quem ainda nao foi aprovado deixaria a pessoa sem saber
 * em que pe esta o proprio cadastro.
 */
class ProdutorPainelController extends Controller
{
    public function __invoke()
    {
        $produtor = Auth::guard('produtor')->user();

        $pedidos = Pedido360::where('produtor_id', $produtor->id)
            ->with('oferta.produto')
            ->latest()
            ->limit(10)
            ->get();

        $aReceber = Parcela360::query()
            ->whereIn('pedido_360_id', Pedido360::where('produtor_id', $produtor->id)->select('id'))
            ->where('situacao', 'aberta')
            ->sum('valor_cents');

        $vencido = Parcela360::query()
            ->whereIn('pedido_360_id', Pedido360::where('produtor_id', $produtor->id)->select('id'))
            ->where('situacao', 'vencida')
            ->sum('valor_cents');

        // O repasse sai do razao com sinal negativo (saida da operacao para o
        // produtor); aqui ele e mostrado como entrada, que e o ponto de vista
        // de quem recebe.
        $recebido = -Lancamento360::query()
            ->whereIn('pedido_360_id', Pedido360::where('produtor_id', $produtor->id)->select('id'))
            ->where('tipo', 'repasse')
            ->sum('valor_cents');

        return view('paginas.produtor.painel', [
            'produtor' => $produtor,
            'pedidos' => $pedidos,
            'aReceberCents' => (int) $aReceber,
            'vencidoCents' => (int) $vencido,
            'recebidoCents' => (int) $recebido,
        ]);
    }
}
