<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;

/**
 * O rastro das acoes administrativas, financeiras e de aceite.
 *
 * So leitura, e de proposito: trilha que a propria tela edita nao serve de
 * trilha. Quem escreve e o App\Support\Auditar, chamado por quem executa a acao.
 */
class AuditoriaController extends Controller
{
    /**
     * Confere a corrente de resumos da trilha inteira.
     *
     * A verificacao existia so no comando de console, ou seja, so quem tem SSH
     * conseguia responder "esta trilha foi adulterada?". E justamente a
     * pergunta que se faz na hora de entregar a trilha para alguem de fora.
     */
    public function conferir()
    {
        $quebrados = \App\Support\Auditar::conferir();

        return back()->with($quebrados === [] ? 'ok' : 'erro', $quebrados === []
            ? 'Trilha conferida: nenhum registro foi alterado ou removido.'
            : 'Atenção: '.count($quebrados).' registro(s) não fecham a corrente. Números: '.implode(', ', array_slice($quebrados, 0, 20)).'.');
    }

    private const POR_PAGINA = 50;

    public function __invoke(Request $request)
    {
        $acao = trim((string) $request->query('acao'));

        $registros = Auditoria::query()
            ->with('staff')
            ->when($acao !== '', fn ($q) => $q->where('acao', $acao))
            ->orderByDesc('ocorreu_em')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('paginas.auditoria.index', [
            'registros' => $registros,
            'acao' => $acao,
            'acoes' => Auditoria::query()->distinct()->orderBy('acao')->pluck('acao'),
        ]);
    }
}
