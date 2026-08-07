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

    /**
     * Os termos da equipe, para o vendedor ler e aceitar.
     *
     * Mesma robustez do aceite da empresa: nome, confirmacao de leitura e o
     * hash do texto exibido, conferido no POST. O aceite entra na mesma tabela
     * de evidencias, com staff_id.
     */
    public function termos()
    {
        $staff = auth('staff')->user();

        return view('paginas.documentos.termos', [
            'documentos' => DocumentoLegal::query()->para('vendedor')->orderBy('titulo')->get(),
            'aceites' => \App\Models\AceiteDocumento::where('staff_id', $staff->id)->pluck('documento_id')->all(),
        ]);
    }

    public function aceitarTermo(Request $pedido, DocumentoLegal $documento)
    {
        abort_unless($documento->ativo && $documento->exige_aceite && $documento->para_vendedor, 404);

        $dados = $pedido->validate([
            'responsavel' => ['required', 'string', 'min:5', 'max:150'],
            'li' => ['accepted'],
            'hash' => ['required', 'string'],
        ], [
            'li.accepted' => 'Confirme que leu o documento.',
        ]);

        if (! hash_equals($documento->hashConteudo(), $dados['hash'])) {
            return back()->with('erro', 'O documento foi atualizado desde a sua leitura. Releia a versão vigente antes de aceitar.');
        }

        $staff = auth('staff')->user();

        $aceite = \App\Models\AceiteDocumento::firstOrCreate(
            ['documento_id' => $documento->id, 'staff_id' => $staff->id, 'cliente_id' => null, 'operador_id' => null],
            [
                'responsavel' => $dados['responsavel'],
                'versao' => $documento->versao,
                'hash_conteudo' => $documento->hashConteudo(),
                'ip_address' => $pedido->ip(),
                'user_agent' => $pedido->userAgent(),
                'aceito_em' => now(),
            ],
        );

        if ($aceite->wasRecentlyCreated) {
            Auditar::registrar('documento.aceito', $documento, [
                'versao' => $aceite->versao,
                'hash_conteudo' => $aceite->hash_conteudo,
            ]);
        }

        return back()->with('ok', 'Aceite registrado.');
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
            'para_empresa' => ['nullable', 'boolean'],
            'para_operador' => ['nullable', 'boolean'],
            'para_vendedor' => ['nullable', 'boolean'],
        ]);

        $documento = DB::transaction(function () use ($dados) {
            DocumentoLegal::where('tipo', $dados['tipo'])->where('ativo', true)->update(['ativo' => false]);

            return DocumentoLegal::create([
                ...$dados,
                'exige_aceite' => (bool) ($dados['exige_aceite'] ?? false),
                'para_empresa' => (bool) ($dados['para_empresa'] ?? false),
                'para_operador' => (bool) ($dados['para_operador'] ?? false),
                'para_vendedor' => (bool) ($dados['para_vendedor'] ?? false),
                'ativo' => true,
            ]);
        });

        Auditar::registrar('documento.publicado', $documento, ['tipo' => $documento->tipo, 'versao' => $documento->versao]);

        return redirect()->route('documentos.index')->with('ok', 'Nova versão publicada.');
    }
}
