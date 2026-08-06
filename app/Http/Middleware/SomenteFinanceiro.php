<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha as rotas que mexem em dinheiro recebido.
 *
 * Confirmar pagamento libera comissao sem que dinheiro tenha entrado, e fechar
 * competencia emite cobranca. Nao e o mesmo tipo de decisao que renomear um
 * servico do catalogo, e por isso nao depende da mesma permissao.
 *
 * Vem depois de `admin` nas rotas, e nao no lugar dele: quem cuida do
 * financeiro tambem precisa ser da administracao.
 */
class SomenteFinanceiro
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('staff')->user()?->podeFinanceiro(), 403);

        return $next($request);
    }
}
