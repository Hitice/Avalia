<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha a rota para quem nao e administracao.
 *
 * Vendedor autenticado e um usuario legitimo do sistema, e por isso `auth:staff`
 * sozinho nao basta aqui. Preco, custo e margem sao justamente o que ele nao
 * pode ver: a tabela de custo interno revelaria a margem da Avalia sobre a
 * propria comissao dele.
 */
class SomenteAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $conta = Auth::guard('staff')->user();

        if (! $conta || ! ($conta->ehAdmin() || $conta->ehSuper())) {
            abort(403, 'Area restrita a administracao.');
        }

        return $next($request);
    }
}
