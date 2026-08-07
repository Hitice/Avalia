<?php

namespace App\Http\Controllers;

use App\Models\Operador;
use App\Support\Auditar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * A propria conta: trocar a senha sem depender de ninguem.
 *
 * Faltava, e a falta era grande: quem esta dentro so conseguia trocar a senha
 * saindo e pedindo "esqueci minha senha", o que e um caminho de recuperacao,
 * nao de manutencao. Vale para as tres naturezas de conta, porque a
 * necessidade e a mesma.
 *
 * A senha atual e exigida mesmo com a sessao aberta: sessao esquecida aberta
 * em maquina compartilhada nao pode virar troca de dono da conta.
 */
class PerfilController extends Controller
{
    public function mostrar()
    {
        return view('paginas.perfil.index', ['conta' => $this->conta()]);
    }

    public function salvarSenha(Request $pedido)
    {
        $dados = $pedido->validate([
            'senha_atual' => ['required', 'string'],
            'senha' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'senha.min' => 'A senha precisa de pelo menos 10 caracteres.',
            'senha.confirmed' => 'As duas senhas não conferem.',
        ]);

        $conta = $this->conta();

        if (! Hash::check($dados['senha_atual'], $conta->senha)) {
            return back()->withErrors(['senha_atual' => 'A senha atual não confere.']);
        }

        $conta->senha = $dados['senha'];
        $conta->save();

        Auditar::registrar('acesso.senha_trocada', $conta);

        // Trocar a senha derruba as OUTRAS sessoes, e a atual sobrevive: quem
        // troca a senha suspeitando de acesso indevido espera exatamente isso,
        // e ser deslogado no ato so faria a pessoa achar que deu errado.
        $conta->revogaSessoes();
        $pedido->session()->put($this->carimbo(), $conta->fresh()->sessao_versao);

        return back()->with('ok', 'Senha alterada. As outras sessões desta conta foram encerradas.');
    }

    private function conta(): object
    {
        return Operador::daSessao()
            ?? auth('staff')->user()
            ?? auth('empresa')->user();
    }

    private function carimbo(): string
    {
        return Operador::daSessao() ? 'versao_operador' : (auth('staff')->check() ? 'versao_staff' : 'versao_empresa');
    }
}
