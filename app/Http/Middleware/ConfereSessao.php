<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida a sessao a cada requisicao contra o estado atual da conta.
 *
 * Existe porque cookie valido nao e o mesmo que acesso permitido. Sem isto,
 * desativar um vendedor ou encerrar um cliente so teria efeito quando o cookie
 * dele expirasse, e ate la ele continuaria operando normalmente.
 *
 * Duas checagens:
 *   1. sessao_versao gravada na sessao == a do banco (revogacao explicita)
 *   2. a conta ainda pode entrar (ativo / situacao)
 */
class ConfereSessao
{
    public function handle(Request $request, Closure $next, string $guarda = 'staff'): Response
    {
        $conta = Auth::guard($guarda)->user();

        if (! $conta) {
            return $next($request);
        }

        // Rele do banco em vez de confiar no model que o guard trouxe. O guard
        // guarda a instancia em memoria, e sob Octane esse processo sobrevive a
        // varias requisicoes, e revogar so teria efeito quando o worker
        // reciclasse. Uma consulta por requisicao e o preco de a revogacao ser
        // imediata de verdade.
        $conta = $conta->fresh();

        $versaoNaSessao = $request->session()->get("versao_{$guarda}");

        // Entrou pelo cookie de "manter conectado": a sessao e nova e ainda nao
        // tem carimbo, porque quem carimba e o formulario de login. Sem esta
        // linha o middleware derruba exatamente o que a lembranca acabou de
        // restaurar, e o efeito nao e so voltar para a tela de entrada: um POST
        // feito nesse instante e descartado antes de chegar ao controller, e o
        // operador ve o formulario voltar sem ter salvado.
        //
        // Aceitar aqui nao afrouxa a revogacao: revogaSessoes() apaga o
        // remember_token junto, entao conta revogada nem chega a autenticar.
        if ($conta && $versaoNaSessao === null && Auth::guard($guarda)->viaRemember()) {
            $request->session()->put("versao_{$guarda}", $conta->sessao_versao);
            $versaoNaSessao = $conta->sessao_versao;
        }

        if (! $conta || $versaoNaSessao !== $conta->sessao_versao || ! $conta->podeEntrar()) {
            Auth::guard($guarda)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('entrar')
                ->with('erro', 'Sua sessao foi encerrada. Entre novamente.');
        }

        // Sessao aberta por operador: a pessoa tambem precisa continuar valida.
        // Desativar o operador derruba a sessao dele na hora, sem tocar na
        // conta master nem nos outros operadores da mesma empresa.
        if ($guarda === 'empresa' && ($idOperador = $request->session()->get('operador_id'))) {
            $operador = \App\Models\Operador::find($idOperador);

            if (! $operador
                || $request->session()->get('versao_operador') !== $operador->sessao_versao
                || ! $operador->podeEntrar()
                || $operador->cliente_id !== $conta->id) {
                Auth::guard($guarda)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('entrar')
                    ->with('erro', 'Sua sessao foi encerrada. Entre novamente.');
            }
        }

        return $next($request);
    }
}
