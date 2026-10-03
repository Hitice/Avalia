<?php

namespace App\Http\Controllers;

use App\Models\Contato;
use App\Models\Lead;
use App\Models\Vinculo;

/** A home do CRM: quantos a casa conhece, e onde cada um esta. */
class CrmController extends Controller
{
    public function inicio()
    {
        return view('paginas.crm.inicio', [
            'contatos' => Contato::count(),
            'porPapel' => Vinculo::selectRaw('papel, count(distinct contato_id) as total')->groupBy('papel')->pluck('total', 'papel'),
            'leadsNovos' => Lead::where('situacao', \App\Enums\SituacaoLead::Novo)->count(),
            'novosNoMes' => Contato::where('created_at', '>=', now()->startOfMonth())->count(),
        ]);
    }
}
