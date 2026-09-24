<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\RegistrarAcesso;
use App\Models\Etiqueta;
use App\Support\CodigoCurto;
use Illuminate\Http\Request;

/**
 * A leitura de uma plaquinha.
 *
 * E a rota mais quente do sistema e a mais importante: ela roda toda vez que
 * alguem encosta o celular numa placa em campo, e uma falha aqui apaga a loja
 * de um cliente do mapa. Por isso ela nao carrega sessao, nao consulta usuario
 * e nao monta nada alem do necessario.
 *
 * NENHUM caminho daqui devolve o 404 cru do framework, com uma excecao: codigo
 * que nao existe. Placa em branco, suspensa ou vencida sao situacoes legitimas
 * do produto, e quem esta do outro lado e um freguês parado no balcao de uma
 * loja. Ele merece uma pagina que explique, e nao um erro.
 */
class RedirecionadorController extends Controller
{
    public function __construct(private readonly RegistrarAcesso $registrar) {}

    public function __invoke(Request $pedido, string $codigo)
    {
        $normalizado = CodigoCurto::normalizar($codigo);
        $etiqueta = $normalizado === '' ? null : Etiqueta::porCodigo($normalizado);

        if (! $etiqueta) {
            return $this->aviso('nao_encontrada', $codigo, 404);
        }

        $estado = $etiqueta->estado();

        if (! $etiqueta->redireciona()) {
            return $this->aviso($estado, $etiqueta->codigo);
        }

        ($this->registrar)($etiqueta, $pedido->userAgent(), $pedido->method());

        /*
         * 302, e nunca 301.
         *
         * O 301 e redirecionamento permanente: navegador, provedor e antivirus
         * guardam o destino e param de perguntar. A plaquinha ficaria congelada
         * no primeiro endereco mesmo depois de trocado, e sem jeito de
         * desfazer, porque o cache mora na maquina de quem leu. Seria o
         * contrario exato do produto que estamos vendendo.
         *
         * `no-store` pela mesma razao, um degrau abaixo: sem ele, uma camada de
         * cache no meio do caminho guarda a resposta por conta propria.
         */
        return redirect()->away($etiqueta->destino, 302)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** A pagina que explica por que a leitura nao virou visita. */
    private function aviso(string $estado, string $codigo, int $status = 200)
    {
        return response()->view('paginas.etiqueta.aviso', [
            'estado' => $estado,
            'codigo' => mb_strtoupper($codigo),
        ], $status);
    }
}
