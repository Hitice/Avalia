<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\ContaAutenticavel;
use App\Http\Controllers\Controller;
use App\Services\ProtecaoLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Porta unica de entrada.
 *
 * Um formulario so resolve as duas naturezas de conta: tenta `staff` e depois
 * `empresa`. Quem entra nao precisa saber em que tabela mora — e o produto que
 * sabe. A separacao rigorosa acontece depois, nos guards e nas policies.
 */
class LoginController extends Controller
{
    /** Ordem de tentativa. Staff primeiro: e a base menor. */
    private const GUARDAS = ['staff', 'empresa'];

    public function __construct(private readonly ProtecaoLogin $protecao) {}

    public function mostrar(): View|RedirectResponse
    {
        if ($destino = $this->jaLogado()) {
            return redirect($destino);
        }

        return view('paginas.acesso.entrar');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'senha' => ['required', 'string'],
            'lembrar' => ['nullable', 'boolean'],
        ], [], ['email' => 'e-mail', 'senha' => 'senha']);

        $this->recusaSeDeCastigo($dados['email'], $request);

        foreach (self::GUARDAS as $guarda) {
            if (! Auth::guard($guarda)->attempt(
                ['email' => $dados['email'], 'password' => $dados['senha']],
                (bool) ($dados['lembrar'] ?? false),
            )) {
                continue;
            }

            $conta = Auth::guard($guarda)->user();

            // Credencial certa mas conta impedida: derruba e explica. Nao conta
            // como falha de senha, entao nao alimenta o castigo.
            if (! $conta->podeEntrar()) {
                Auth::guard($guarda)->logout();

                throw ValidationException::withMessages([
                    'email' => $conta->motivoSuspensao() ?? 'Esta conta esta desativada.',
                ]);
            }

            return $this->concluiEntrada($request, $guarda, $conta);
        }

        $this->protecao->falhou($dados['email'], $request);

        // Mensagem unica para senha errada e conta inexistente: dizer qual dos
        // dois falhou entrega a lista de e-mails validos a quem esta sondando.
        throw ValidationException::withMessages([
            'email' => 'E-mail ou senha invalidos.',
        ]);
    }

    public function sair(Request $request): RedirectResponse
    {
        foreach (self::GUARDAS as $guarda) {
            if (Auth::guard($guarda)->check()) {
                Auth::guard($guarda)->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('entrar');
    }

    /** Fixa a sessao no estado atual da conta e manda para a area certa. */
    private function concluiEntrada(Request $request, string $guarda, ContaAutenticavel $conta): RedirectResponse
    {
        $this->protecao->acertou($conta->email, $request);

        // Troca o id da sessao: sem isso, um id capturado antes do login
        // continuaria valido depois dele (session fixation).
        $request->session()->regenerate();

        // Carimbo lido pelo ConfereSessao a cada requisicao.
        $request->session()->put("versao_{$guarda}", $conta->sessao_versao);

        $conta->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();

        return redirect()->intended($this->destinoDe($guarda));
    }

    private function recusaSeDeCastigo(string $email, Request $request): void
    {
        if (! $segundos = $this->protecao->bloqueadoPor($email, $request)) {
            return;
        }

        $espera = $segundos >= 60
            ? ceil($segundos / 60).' minuto(s)'
            : $segundos.' segundo(s)';

        throw ValidationException::withMessages([
            'email' => "Tentativas demais. Tente de novo em {$espera}.",
        ]);
    }

    private function jaLogado(): ?string
    {
        foreach (self::GUARDAS as $guarda) {
            if (Auth::guard($guarda)->check()) {
                return $this->destinoDe($guarda);
            }
        }

        return null;
    }

    private function destinoDe(string $guarda): string
    {
        return $guarda === 'staff' ? route('painel') : route('empresa.painel');
    }
}
