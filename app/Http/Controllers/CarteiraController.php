<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use Illuminate\Support\Facades\Auth;

/**
 * A carteira do vendedor: as empresas dele e o que ele tem a receber.
 *
 * Tela separada das de administracao de proposito, e nao um filtro aplicado
 * naquelas. As telas de admin mostram custo do fornecedor, lucro e margem, que
 * sao internos e nunca vao para o vendedor (PDD.md, secao 6). Reaproveitar a
 * mesma tela com condicionais deixaria cada campo novo a um `@if` de distancia
 * de vazar.
 *
 * O vendedor so enxerga o proprio `staff_id`: nao existe parametro de rota que
 * escolha a carteira, entao nao ha como pedir a de outro trocando a URL.
 */
class CarteiraController extends Controller
{
    public function __invoke()
    {
        $vendedor = Auth::guard('staff')->user();

        $empresas = Cliente::query()
            ->with('plano')
            ->where('vendedor_id', $vendedor->id)
            ->orderBy('razao_social')
            ->get();

        $faturas = Fatura::query()
            ->with('cliente')
            ->where('vendedor_id', $vendedor->id)
            ->orderByDesc('competencia')
            ->get();

        $competencia = Consulta::competenciaDe();

        return view('paginas.carteira.index', [
            'vendedor' => $vendedor,
            'empresas' => $empresas,
            'faturas' => $faturas,
            'competencia' => $competencia,
            // Consumo do mes por empresa, que e sobre o que a comissao vai
            // incidir quando a competencia fechar.
            'consumo' => Consulta::query()
                ->whereIn('cliente_id', $empresas->pluck('id'))
                ->where('competencia', $competencia)
                ->selectRaw('cliente_id, sum(preco_cents) as total')
                ->groupBy('cliente_id')
                ->pluck('total', 'cliente_id'),
            'aReceber' => (int) $faturas->whereNotNull('comissao_liberada_em')->sum('comissao_cents'),
            'aConfirmar' => (int) $faturas->whereNull('comissao_liberada_em')->sum('comissao_cents'),
        ]);
    }
}
