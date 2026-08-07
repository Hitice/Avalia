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
 * `empresa`. Quem entra nao precisa saber em que tabela mora: e o produto que
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

        // Operador: mesma porta, mas quem abre a sessao da area e a EMPRESA
        // dele, com a pessoa marcada na sessao. Sem "manter conectado" de
        // proposito: o cookie de lembranca restauraria a sessao sem a marca e
        // o operador viraria a conta master em silencio.
        if ($resposta = $this->entrarComoOperador($request, $dados)) {
            return $resposta;
        }

        $this->protecao->falhou($dados['email'], $request);

        // Mensagem unica para senha errada e conta inexistente: dizer qual dos
        // dois falhou entrega a lista de e-mails validos a quem esta sondando.
        throw ValidationException::withMessages([
            'email' => 'E-mail ou senha inválidos.',
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

    private function entrarComoOperador(Request $request, array $dados): ?RedirectResponse
    {
        $operador = \App\Models\Operador::where('email', $dados['email'])->first();

        if (! $operador || ! \Illuminate\Support\Facades\Hash::check($dados['senha'], $operador->senha)) {
            return null;
        }

        if (! $operador->podeEntrar()) {
            throw ValidationException::withMessages([
                'email' => $operador->motivoSuspensao() ?? 'Esta conta esta desativada.',
            ]);
        }

        $this->protecao->acertou($operador->email, $request);
        Auth::guard('empresa')->login($operador->cliente);
        $request->session()->regenerate();
        $request->session()->put('versao_empresa', $operador->cliente->sessao_versao);
        $request->session()->put('operador_id', $operador->id);
        $request->session()->put('versao_operador', $operador->sessao_versao);

        $operador->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();

        // A ciencia dos termos e por pessoa: operador sem o proprio aceite
        // comeca pelos documentos, como a conta master comecaria.
        if (! $operador->aceitouObrigatorios()) {
            return redirect()->route('empresa.documentos');
        }

        return redirect()->route('empresa.painel');
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

        // Contrato pendente e a primeira tela, antes de qualquer destino
        // guardado: o aceite nao espera a pessoa achar o menu Documentos.
        if ($conta instanceof \App\Models\Cliente && ! $conta->documentosObrigatoriosAceitos()) {
            return redirect()->route('empresa.documentos');
        }

        // O mesmo vale para o vendedor com termo da equipe pendente.
        if ($conta instanceof \App\Models\Staff && ! $conta->aceitouObrigatorios()) {
            return redirect()->route('termos');
        }

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
