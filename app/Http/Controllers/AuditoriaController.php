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
