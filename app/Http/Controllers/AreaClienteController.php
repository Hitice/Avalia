<?php

namespace App\Http\Controllers;

use App\Actions\Documentos\RegistrarAceiteDocumento;
use App\Models\DocumentoLegal;

/** Painel restrito à própria empresa contratante. */
class AreaClienteController extends Controller
{
    public function painel()
    {
        $empresa = auth('empresa')->user();
        $documentos = DocumentoLegal::query()->where('ativo', true)->orderBy('titulo')->get();
        $aceites = $empresa->aceitesDocumentos()->pluck('documento_id')->all();

        return view('paginas.empresa.painel', compact('empresa', 'documentos', 'aceites'));
    }

    public function aceitar(DocumentoLegal $documento, RegistrarAceiteDocumento $registrar)
    {
        $registrar(auth('empresa')->user(), $documento);

        return back()->with('ok', 'Aceite registrado.');
    }
}
