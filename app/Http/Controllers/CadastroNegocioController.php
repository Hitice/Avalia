<?php

namespace App\Http\Controllers;

use App\Actions\Negocios\GerarLinkDeAvaliacao;
use App\Enums\SituacaoNegocio;
use App\Exceptions\Recusa;
use App\Http\Requests\NegocioRequest;
use App\Models\Negocio;
use App\Services\Google\BuscarLugar;
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
    /*
    |--------------------------------------------------------------------------
    | A ferramenta publica do link de avaliacao
    |--------------------------------------------------------------------------
    |
    | Pede contato antes de pesquisar, e isso NAO e barreira de marketing: cada
    | busca e cobrada pelo Google, e ferramenta aberta sem contato vira fatura
    | paga pela casa por conta de quem passa na rua. Com o contato, o custo vira
    | cliente na base de negocios.
    */

    public function buscarAvaliacao(Request $pedido, BuscarLugar $buscar, GerarLinkDeAvaliacao $gerar)
    {
        $dados = $this->conferirPedido($pedido);

        try {
            $lugares = $buscar($dados['nome'], $dados['cidade'] ?? null);
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage())->withInput();
        }

        if (count($lugares) === 1) {
            return $this->entregarAvaliacao($gerar, $lugares[0], $dados);
        }

        return back()->with('lugares', $lugares)->withInput();
    }

    public function gerarAvaliacao(Request $pedido, GerarLinkDeAvaliacao $gerar)
    {
        $dados = $this->conferirPedido($pedido, ['place_id' => ['required', 'string', 'max:255']]);

        return $this->entregarAvaliacao($gerar, [
            'place_id' => $dados['place_id'],
            'nome' => $dados['nome'],
        ], $dados);
    }

    /** @return array<string, mixed> */
    private function conferirPedido(Request $pedido, array $extra = []): array
    {
        return $pedido->validate($extra + [
            'nome' => ['required', 'string', 'max:150'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150'],

            // Mesma armadilha do resto dos formularios abertos.
            'assunto' => ['prohibited'],
        ]);
    }

    /**
     * Grava o negocio na base e devolve o link curto.
     *
     * Reaproveita o cadastro do mesmo e-mail em vez de criar outro: quem gera o
     * link duas vezes nao e dois clientes, e base com duplicata faz o
     * atendimento ligar para a mesma loja duas vezes.
     *
     * @param  array{place_id: string, nome: string}  $lugar
     * @param  array<string, mixed>  $dados
     */
    private function entregarAvaliacao(GerarLinkDeAvaliacao $gerar, array $lugar, array $dados)
    {
        $email = mb_strtolower(trim((string) $dados['email']));

        $negocio = Negocio::firstOrNew(['email' => $email]);

        $negocio->fill([
            'nome' => $negocio->exists ? $negocio->nome : $lugar['nome'],
            'responsavel' => $negocio->responsavel ?: $lugar['nome'],
            'whatsapp' => preg_replace('/\D/', '', (string) $dados['whatsapp']),
            'cidade' => $negocio->cidade ?: ($dados['cidade'] ?? null),
            'situacao' => $negocio->exists ? $negocio->situacao->value : SituacaoNegocio::Recebido->value,
            'origem' => $negocio->origem ?: 'ferramenta-avaliacao',
        ])->save();

        try {
            $link = $gerar($lugar['place_id'], $lugar['nome'], $negocio);
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage())->withInput();
        }

        return back()
            ->with('ok', 'Pronto. Este é o link de avaliação de '.$lugar['nome'].'.')
            ->with('linkPronto', route('l', ['codigo' => $link->codigo]));
    }
}
