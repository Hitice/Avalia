<?php

namespace App\Http\Controllers;

use App\Models\InteressadoCobranca;
use App\Models\Produtor;
use App\Support\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Entrada e cadastro do produtor no Avalia 360.
 *
 * O cadastro e auto-servico e curto de proposito: nome, documento, contato e
 * senha. Pedir dados bancarios e faturamento antes de a pessoa conhecer o
 * painel e o jeito mais rapido de perder um produtor que ainda esta decidindo.
 * O que falta para vender e pedido depois, quando a subconta for aberta.
 *
 * Quem se cadastra entra como `pendente`: ve o painel, entende o que falta, e
 * nao publica oferta nem recebe enquanto a aprovacao nao sair.
 */
class ProdutorAcessoController extends Controller
{
    public function mostrarCadastro()
    {
        return view('paginas.produtor.criar-conta');
    }

    public function cadastrar(Request $pedido)
    {
        if ($pedido->filled('site')) {
            return redirect()->route('produtor.painel');
        }

        $pedido->merge([
            'documento' => Documento::normalizarCnpj($pedido->input('documento')),
            'email' => mb_strtolower(trim((string) $pedido->input('email'))),
            'whatsapp' => preg_replace('/\D/', '', (string) $pedido->input('whatsapp')) ?? '',
        ]);

        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'min:3', 'max:150'],
            'documento' => [
                'required', 'string',
                fn ($a, $v, $falhou) => Documento::documentoValido($v) ? null : $falhou('Confira o CPF ou o CNPJ.'),
            ],
            'email' => ['required', 'email', 'max:150', Rule::unique('produtores', 'email')->whereNull('deleted_at')],
            'whatsapp' => ['required', 'string', 'min:10', 'max:11'],
            // Oito e o minimo que o resto da casa exige. A confirmacao existe
            // porque errar a senha no cadastro so aparece no proximo login, e
            // ai a pessoa ja saiu da tela.
            'senha' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Já existe uma conta com este e-mail. Se for sua, entre por aqui.',
            'senha.confirmed' => 'As senhas não conferem.',
            'senha.min' => 'A senha precisa de pelo menos 8 caracteres.',
        ]);

        $produtor = Produtor::create($dados + ['situacao' => 'pendente']);

        // `sessao_versao` tem valor padrao no banco, entao o objeto recem
        // criado ainda nao o conhece. Sem este refresh, a sessao guardava null
        // e o ConfereSessao derrubava a pessoa na primeira tela depois do
        // cadastro: entrava e caia fora, sem erro nenhum aparecer.
        $produtor->refresh();

        // Liga o cadastro ao pre-cadastro que veio da pagina publica, quando o
        // e-mail for o mesmo: e o que permite saber depois qual porta trouxe
        // cada produtor.
        $interessado = InteressadoCobranca::firstWhere('email', $dados['email']);

        if ($interessado) {
            $produtor->update(['interessado_cobranca_id' => $interessado->id]);
        }

        Auth::guard('produtor')->login($produtor);
        $pedido->session()->put('versao_produtor', $produtor->sessao_versao);
        $pedido->session()->regenerate();

        return redirect()->route('produtor.painel');
    }

    public function mostrarEntrada()
    {
        return view('paginas.produtor.entrar');
    }

    public function entrar(Request $pedido)
    {
        $dados = $pedido->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $produtor = Produtor::firstWhere('email', mb_strtolower(trim($dados['email'])));

        // Resposta identica exista ou nao a conta: dizer "e-mail não cadastrado"
        // transforma a tela de login numa consulta de quem e produtor aqui.
        if (! $produtor || ! Auth::guard('produtor')->attempt(
            ['email' => mb_strtolower(trim($dados['email'])), 'password' => $dados['senha']],
            $pedido->boolean('lembrar'),
        )) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha não conferem.']);
        }

        if (! $produtor->podeEntrar()) {
            Auth::guard('produtor')->logout();

            throw ValidationException::withMessages([
                'email' => $produtor->motivoSuspensao() ?? 'Esta conta não pode entrar.',
            ]);
        }

        $pedido->session()->put('versao_produtor', $produtor->sessao_versao);
        $pedido->session()->regenerate();

        return redirect()->intended(route('produtor.painel'));
    }

    public function sair(Request $pedido)
    {
        Auth::guard('produtor')->logout();
        $pedido->session()->invalidate();
        $pedido->session()->regenerateToken();

        return redirect()->route('produtor.entrar');
    }
}
