<?php

namespace App\Http\Controllers;

use App\Actions\Documentos\RegistrarAceiteDocumento;
use App\Models\Consulta;
use App\Models\DocumentoLegal;

/** Painel restrito à própria empresa contratante. */
class AreaClienteController extends Controller
{
    public function painel()
    {
        $empresa = auth('empresa')->user();
        $documentos = DocumentoLegal::query()->where('ativo', true)->orderBy('titulo')->get();
        $aceites = $empresa->aceitesDocumentos()->pluck('documento_id')->all();
        $competencia = Consulta::competenciaDe();
        $plano = $empresa->plano?->load('franquias.servico');
        $uso = $empresa->consultas()
            ->where('competencia', $competencia)
            ->selectRaw('servico_id, count(*) as quantidade')
            ->groupBy('servico_id')
            ->pluck('quantidade', 'servico_id');
        $faturas = $empresa->faturas()->with('cobrancaAsaas')->orderByDesc('competencia')->limit(6)->get();

        return view('paginas.empresa.painel', compact(
            'empresa', 'documentos', 'aceites', 'competencia', 'plano', 'uso', 'faturas',
        ));
    }

    public function aceitar(DocumentoLegal $documento, RegistrarAceiteDocumento $registrar)
    {
        $registrar(auth('empresa')->user(), $documento);

        return back()->with('ok', 'Aceite registrado.');
    }
}
