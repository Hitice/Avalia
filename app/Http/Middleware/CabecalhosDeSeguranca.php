<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabecalhos que o navegador respeita e que a aplicacao nao precisa vigiar.
 *
 * Cada um fecha uma classe inteira de ataque de graca, do lado do cliente:
 *
 *   a politica de conteudo impede que script de outra origem execute, que e a
 *   diferenca entre um campo mal escapado e uma sessao roubada;
 *   negar enquadramento a terceiros impede que a tela seja embutida em pagina
 *   alheia para capturar clique (a propria origem pode, porque o visor de
 *   laudo abre o PDF em iframe do proprio site);
 *   a politica de referencia impede que o endereco de uma fatura ou consulta
 *   vaze no cabecalho de uma requisicao para fora;
 *   `nosniff` impede que o navegador adivinhe o tipo de um arquivo e execute o
 *   que deveria ser download.
 *
 * A politica de conteudo aceita estilo e script embutidos porque o tema usa os
 * dois. Apertar isso exige tirar todo `x-on:` e todo `style=` do Blade, o que e
 * trabalho de outra ordem e nao entrega a mesma proporcao de protecao.
 */
class CabecalhosDeSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        $politica = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            // O PROPRIO site pode se emoldurar: o visor de laudo abre o PDF
            // num iframe da mesma origem. Terceiros continuam proibidos, que e
            // o que o cabecalho existe para impedir (clickjacking e por pagina
            // de fora, nao pela nossa).
            "frame-ancestors 'self'",
            "base-uri 'self'",
        ]);

        foreach ([
            'Content-Security-Policy' => $politica,
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ] as $cabecalho => $valor) {
            $resposta->headers->set($cabecalho, $valor);
        }

        return $resposta;
    }
}
