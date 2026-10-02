<?php

namespace App\Http\Middleware;

use App\Helpers\MenuHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha o produto para quem da equipe nao o acessa.
 *
 * Decide pela rota, como a lateral: o que e do Sales esta em
 * MenuHelper::ROTAS_SALES, o resto e One. Conta, sair e termos nao sao de
 * produto nenhum. Cliente e produtor passam: o que limita cada um e o dono
 * gravado no codigo, e nao esta chave.
 */
class AcessoAoProduto
{
    private const NEUTRAS = ['perfil', 'sair', 'termos', 'senha.'];

    public function handle(Request $request, Closure $next): Response
    {
        $conta = Auth::guard('staff')->user();
        $rota = (string) $request->route()?->getName();

        if (! $conta || $this->neutra($rota)) {
            return $next($request);
        }

        if (MenuHelper::naSales()) {
            abort_unless($conta->acessa('sales'), 403, 'Sem acesso ao Avalia Sales.');
        } else {
            abort_unless($conta->acessa('one'), 403, 'Sem acesso ao Avalia One.');
        }

        return $next($request);
    }

    private function neutra(string $rota): bool
    {
        foreach (self::NEUTRAS as $prefixo) {
            if ($rota === $prefixo || str_starts_with($rota, $prefixo)) {
                return true;
            }
        }

        return false;
    }
}
