<?php

namespace App\Http\Controllers;

use App\Models\DocumentoLegal;
use App\Support\Auditar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentoController extends Controller
{
    /** O documento em PDF, como a empresa o recebe. */
    public function pdf(\App\Models\DocumentoLegal $documento)
    {
        return response(\App\Support\DocumentoPdf::documento($documento), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documento->tipo.'-v'.$documento->versao.'.pdf"',
        ]);
    }

    public function index()
    {
        return view('paginas.documentos.index', ['documentos' => DocumentoLegal::orderBy('tipo')->orderByDesc('created_at')->get()]);
    }

    public function criar()
    {
        return view('paginas.documentos.formulario');
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'tipo' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
            'versao' => ['required', 'string', 'max:40'],
            'conteudo' => ['required', 'string'],
            'exige_aceite' => ['nullable', 'boolean'],
        ]);

        $documento = DB::transaction(function () use ($dados) {
            DocumentoLegal::where('tipo', $dados['tipo'])->where('ativo', true)->update(['ativo' => false]);

            return DocumentoLegal::create([
                ...$dados,
                'exige_aceite' => (bool) ($dados['exige_aceite'] ?? false),
                'ativo' => true,
            ]);
        });

        Auditar::registrar('documento.publicado', $documento, ['tipo' => $documento->tipo, 'versao' => $documento->versao]);

        return redirect()->route('documentos.index')->with('ok', 'Nova versão publicada.');
    }
}
