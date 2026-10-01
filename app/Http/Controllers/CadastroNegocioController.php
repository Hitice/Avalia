<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoNegocio;
use App\Http\Requests\NegocioRequest;
use App\Models\Negocio;
use App\Support\Auditar;
use Illuminate\Http\Request;

/**
 * O formulario que o proprio cliente preenche, por link.
 *
 * Publico e sem login, pela mesma razao do checkout do Gestor: o link vai por
 * WhatsApp para o dono de uma loja, e exigir conta antes de pedir o nome do
 * negocio e o jeito mais rapido de nao receber nada. O que protege e o teto por
 * origem na rota e o campo armadilha.
 *
 * Existe porque esses dados vinham por audio e foto de bloco de papel, e a casa
 * digitava depois. Cada ida e volta para confirmar um horario ou um CEP custa
 * dias no cadastro do Google.
 */
class CadastroNegocioController extends Controller
{
    public function mostrar(Request $pedido)
    {
        return view('paginas.site.cadastro-negocio', [
            // A origem vem do link, para saber quem distribuiu: o vendedor manda o
            // dele e a conta bate no fim do mes. Sem isso o cadastro chega orfao,
            // que e o mesmo problema que a venda de plaquinha ja teve.
            'origem' => substr((string) $pedido->query('origem', ''), 0, 60),
        ]);
    }

    public function cadastrar(NegocioRequest $pedido)
    {
        $negocio = Negocio::create($pedido->validated() + [
            'situacao' => SituacaoNegocio::Recebido->value,
            'origem' => substr((string) $pedido->input('origem', 'link'), 0, 60) ?: 'link',
        ]);

        // Sem nome de pessoa no registro: a trilha vive para sempre e o dado
        // pessoal tem prazo. O id basta para achar a ficha.
        Auditar::registrar('negocio.cadastrado', $negocio, [
            'origem' => $negocio->origem,
            'cidade' => $negocio->cidade,
        ]);

        return redirect()->route('cadastro-negocio', ['origem' => $negocio->origem])
            ->with('ok', 'Recebido. A gente fala com você pelo WhatsApp para fechar o cadastro.');
    }
}
