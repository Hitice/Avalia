<?php

namespace App\Http\Controllers;

use App\Models\Interessado;
use App\Support\Auditar;
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

        // De onde o pedido veio, para saber qual porta converte. Lista
        // fechada: origem e rotulo nosso, nao campo livre de formulario.
        $origem = $pedido->input('origem') === 'login' ? 'login' : 'campanha';

        Interessado::create($dados + ['origem' => $origem]);

        return back()->with('interesse_ok', true);
    }

    /**
     * Tira o pedido da fila do painel depois do retorno feito.
     *
     * Marca, nao apaga: quem pediu contato e quando segue no banco para medir
     * qual porta converte. A trilha guarda quem atendeu.
     */
    public function atender(Interessado $interessado)
    {
        if ($interessado->atendido_em === null) {
            $interessado->update(['atendido_em' => now()]);
            Auditar::registrar('interessado.atendido', $interessado);
        }

        return back()->with('ok', 'Pedido de '.$interessado->nome.' marcado como atendido.');
    }
}
