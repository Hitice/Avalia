<?php

namespace App\Http\Controllers;

use App\Models\Interessado;
use Illuminate\Http\Request;

/**
 * Recebe o pedido de contato da pagina publica.
 *
 * O dado entra no nosso banco e a conversa comeca do nosso lado. A alternativa,
 * abrir o WhatsApp com nome e telefone na URL, quebraria a regra que vale para
 * o sistema inteiro: dado pessoal nao viaja em URL de conversa.
 */
class InteresseController extends Controller
{
    public function salvar(Request $pedido)
    {
        // Campo que nenhuma pessoa ve nem preenche. Robo de formulario preenche
        // tudo, entao valor aqui e lixo automatizado: finge sucesso e descarta,
        // porque responder "detectei voce" e ensinar o robo a melhorar.
        if ($pedido->filled('site')) {
            return back()->with('interesse_ok', true);
        }

        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'min:3', 'max:120'],
            'empresa' => ['required', 'string', 'min:2', 'max:150'],
            'telefone' => ['required', 'string', 'min:10', 'max:20'],
            'email' => ['required', 'email', 'max:150'],
            'funcionarios' => ['required', 'in:Até 5,6 a 20,21 a 50,Mais de 50'],
        ], [
            'nome.required' => 'Diga como podemos te chamar.',
            'empresa.required' => 'Informe o nome da empresa.',
            'telefone.min' => 'Informe o telefone com DDD.',
            'email.email' => 'Confira o e-mail informado.',
            'funcionarios.required' => 'Escolha uma faixa.',
        ]);

        Interessado::create($dados + ['origem' => 'campanha']);

        return back()->with('interesse_ok', true);
    }
}
