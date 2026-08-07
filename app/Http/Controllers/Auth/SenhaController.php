<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auditar;
use App\Support\Convite;
use Illuminate\Http\Request;

/**
 * Define a senha a partir do link do convite.
 *
 * A porta e publica porque quem chega ainda nao tem como se autenticar, mas
 * nao e aberta: a assinatura temporaria (middleware signed) barra link forjado
 * ou vencido, e o carimbo barra link ja usado. Sao as duas mortes do convite,
 * e as duas sao conferidas de novo no POST, porque validar so na tela e
 * validar nada.
 */
class SenhaController extends Controller
{
    public function mostrar(Request $pedido, string $guarda, int $id)
    {
        $conta = $this->contaValida($pedido, $guarda, $id);

        return view('paginas.acesso.definir-senha', [
            'nome' => $conta->nome ?? $conta->razao_social,
            // O POST volta para a mesma URL assinada: a assinatura cobre o
            // formulario inteiro, sem campo escondido para adulterar.
            'destino' => $pedido->fullUrl(),
        ]);
    }

    public function salvar(Request $pedido, string $guarda, int $id)
    {
        $conta = $this->contaValida($pedido, $guarda, $id);

        $pedido->validate([
            'senha' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'senha.min' => 'A senha precisa de pelo menos 10 caracteres.',
            'senha.confirmed' => 'As duas senhas não conferem.',
        ]);

        $conta->senha = $pedido->input('senha');
        $conta->save();

        // Sessao antiga e cookie de lembranca morrem junto com o link: se o
        // convite foi reenviado por suspeita de vazamento, nada sobrevive.
        $conta->revogaSessoes();

        Auditar::registrar('acesso.senha_definida', $conta, ['guarda' => $guarda]);

        return redirect()->route('entrar')->with('ok', 'Senha definida. Entre com ela.');
    }

    private function contaValida(Request $pedido, string $guarda, int $id): object
    {
        abort_unless(in_array($guarda, ['staff', 'empresa', 'operador'], true), 404);

        $conta = Convite::conta($guarda, $id);

        abort_if($conta === null, 404);

        // Link de convite anterior a troca de senha nao vale mais.
        abort_unless(hash_equals(Convite::carimbo($conta), (string) $pedido->query('k')), 403);

        return $conta;
    }
}
