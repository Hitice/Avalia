<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use Illuminate\Support\Facades\Auth;

class PainelController extends Controller
{
    public function __invoke()
    {
        $staff = Auth::guard('staff')->user();
        $competencia = Consulta::competenciaDe();
        $faturas = Fatura::query()->when(! $staff->ehAdmin(), fn ($q) => $q->where('vendedor_id', $staff->id));
        $clientes = Cliente::query()->when(! $staff->ehAdmin(), fn ($q) => $q->where('vendedor_id', $staff->id));

        return view('paginas.painel', [
            'staff' => $staff,
            'competencia' => $competencia,
            'clientesAtivos' => (clone $clientes)->where('situacao', 'ativo')->count(),
            'inadimplentes' => (clone $clientes)->where('situacao', 'inadimplente')->count(),
            'aReceber' => (clone $faturas)->whereIn('situacao_pagamento', [Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO])->sum('total_cents'),
            'vencido' => (clone $faturas)->where('situacao_pagamento', Fatura::PAGAMENTO_VENCIDO)->sum('total_cents'),
            'comissaoLiberada' => (clone $faturas)->whereNotNull('comissao_liberada_em')->sum('comissao_cents'),
            'consultas' => Consulta::query()->when(! $staff->ehAdmin(), fn ($q) => $q->whereIn('cliente_id', (clone $clientes)->select('id')))->where('competencia', $competencia)->count(),
        ]);
    }
}
