<?php

namespace App\Http\Controllers;

use App\Actions\Planilha\ImportarPlanilha;
use App\Actions\Planilha\MontarPlanilha;
use App\Http\Requests\PlanilhaRequest;
use App\Models\Catalogo;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leva o modulo para o Excel e traz de volta.
 *
 * So fluxo HTTP: entregar o arquivo e traduzir o resultado em mensagem. Como se
 * le e se escreve planilha vive em App\Actions\Planilha.
 */
class PlanilhaController extends Controller
{
    public function exportar(MontarPlanilha $montar): StreamedResponse
    {
        $conteudo = $montar();

        return response()->streamDownload(
            fn () => print $conteudo,
            'avalia-catalogo-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function importar(PlanilhaRequest $request, ImportarPlanilha $importar)
    {
        $catalogo = Catalogo::vigente();

        if (! $catalogo) {
            return back()->with('erro', 'Nao ha catalogo para receber a importacao.');
        }

        $resultado = $importar($catalogo, $request->caminho());

        if ($resultado['erro']) {
            return back()->with('erro', $resultado['erro']);
        }

        if ($resultado['atualizados'] === 0) {
            return back()->with('ok', 'Planilha lida: nenhum valor diferente do que ja esta cadastrado.');
        }

        return back()->with('ok', sprintf(
            '%d preco(s) atualizados pela planilha.%s',
            $resultado['atualizados'],
            $resultado['ignorados'] > 0
                ? " {$resultado['ignorados']} linha(s) com codigo desconhecido foram ignoradas."
                : '',
        ));
    }
}
