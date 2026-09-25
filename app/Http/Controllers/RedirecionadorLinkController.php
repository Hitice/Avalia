<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Support\CodigoCurto;
use App\Support\Visitante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A abertura de um link encurtado.
 *
 * Irma da leitura de etiqueta, e com as mesmas regras, porque os riscos sao os
 * mesmos: o endereco ja pode estar gravado numa tag na mao de alguem, e nao ha
 * como pedir para tentar de novo.
 */
class RedirecionadorLinkController extends Controller
{
    public function __invoke(Request $pedido, string $codigo)
    {
        $normalizado = CodigoCurto::normalizar($codigo);
        $link = $normalizado === '' ? null : Link::porCodigo($normalizado);

        if (! $link || ! $link->ativo) {
            return response()->view('paginas.etiqueta.aviso', [
                'estado' => $link ? 'suspensa' : 'nao_encontrada',
                'codigo' => mb_strtoupper($codigo),
            ], $link ? 200 : 404);
        }

        $this->contar($link, $pedido);

        /*
         * 302 e `no-store`, nunca 301.
         *
         * O 301 e permanente: navegador e provedor guardam o destino e param
         * de perguntar, e o link ficaria congelado no primeiro endereco mesmo
         * depois de trocado, sem jeito de desfazer, porque o cache mora na
         * maquina de quem abriu.
         */
        return redirect()->away($link->destino, 302)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Contar nunca derruba o redirecionamento: o produto e levar, nao medir. */
    private function contar(Link $link, Request $pedido): void
    {
        if ($pedido->method() === 'HEAD' || Visitante::ehRobo($pedido->userAgent())) {
            return;
        }

        try {
            // Pelo query builder, e nao pelo model: `save()` dispararia o
            // evento que limpa o cache do destino, a cada clique.
            DB::table('links')->where('id', $link->id)->update([
                'cliques' => DB::raw('cliques + 1'),
                'ultimo_clique_em' => now(),
            ]);
        } catch (\Throwable $erro) {
            Log::warning('Falhou ao contar clique de link', ['link_id' => $link->id, 'erro' => $erro->getMessage()]);
        }
    }
}
