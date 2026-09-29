<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha o caixa da sociedade.
 *
 * Aporte, retirada e quanto a empresa deve a cada socio nao sao operacao do
 * produto: sao a conta dos donos. Nem todo administrador precisa ver, e nem
 * todo socio opera o sistema.
 *
 * Vem depois de `admin` nas rotas, e nao no lugar dele, pelo mesmo motivo de
 * `financeiro`.
 */
class SomenteSocios
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('staff')->user()?->podeSocios(), 403);

        return $next($request);
    }
}
