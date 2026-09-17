<?php

namespace App\Http\Controllers;

use App\Actions\Cobranca\AbrirPedido;
use App\Actions\Cobranca\EmitirCobrancaDaParcela;
use App\Models\Oferta360;
use App\Models\Pedido360;
use App\Support\AnaliseDeCredito;
use App\Support\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * O checkout publico de uma oferta do Avalia 360.
 *
 * Uma pagina so, com os passos visiveis de uma vez. Wizard de quatro telas
 * perde gente em cada troca, e aqui cada abandono e uma venda que o produtor
 * nao faz.
 *
 * A ordem do processamento nao muda por causa disso: analise primeiro,
 * contrato depois, cobranca por ultimo. Cobranca emitida antes do aceite e
 * boleto que o cliente recebe sem ter concordado com nada.
 *
 * O link e publico e nao tem login: qualquer pessoa com o endereco abre. Por
 * isso ele mostra o que o produtor vende, e nunca o que outro cliente comprou.
 */
class CheckoutController extends Controller
{
    public function mostrar(string $slug)
    {
        $oferta = Oferta360::where('slug', $slug)->where('ativa', true)->firstOrFail();

        abort_unless($oferta->produto->ativo && $oferta->produto->produtor->podeVender(), 404);

        return view('paginas.checkout', [
            'oferta' => $oferta,
            'entradaMinima' => AnaliseDeCredito::entradaMinima($oferta->valor_cents),
        ]);
    }

    public function fechar(Request $pedidoHttp, string $slug, AbrirPedido $abrir, EmitirCobrancaDaParcela $emitir)
    {
        $oferta = Oferta360::where('slug', $slug)->where('ativa', true)->firstOrFail();

        // As mesmas guardas de `mostrar()`, de novo: o produto pode ter sido
        // desativado, ou o produtor bloqueado, entre abrir a pagina e apertar
        // o botao. Sem isto, a venda era gravada com contrato aceito e ficava
        // sem boleto para sempre, mostrando "Compra registrada" para quem
        // nunca receberia cobranca.
        abort_unless($oferta->produto->ativo && $oferta->produto->produtor->podeVender(), 404);

        if ($pedidoHttp->filled('site')) {
            return back()->with('checkout_ok', true);
        }

        $pedidoHttp->merge([
            'documento' => Documento::normalizarCnpj($pedidoHttp->input('documento')),
            'email' => mb_strtolower(trim((string) $pedidoHttp->input('email'))),
        ]);

        $dados = $pedidoHttp->validate([
            'nome' => ['required', 'string', 'min:3', 'max:150'],
            'documento' => [
                'required', 'string',
                fn ($a, $v, $falhou) => Documento::documentoValido($v) ? null : $falhou('Confira o CPF: os dígitos não fecham.'),
            ],
            'email' => ['required', 'email', 'max:150'],
            'telefone' => ['required', 'string', 'min:10', 'max:20'],
            'nascimento' => ['required', 'date', 'before:today'],
            'cep' => ['required', 'string', 'max:9'],
            'logradouro' => ['required', 'string', 'max:150'],
            'numero' => ['required', 'string', 'max:20'],
            'bairro' => ['required', 'string', 'max:100'],
            'cidade' => ['required', 'string', 'max:100'],
            'uf' => ['required', 'string', 'size:2'],
            'melhor_dia' => ['required', 'integer', 'min:1', 'max:28'],
            // O aceite e um campo obrigatorio, e nao uma caixa pre-marcada:
            // aceite que ja vem marcado nao prova concordancia nenhuma.
            'aceite' => ['required', Rule::in(['1'])],
        ], [
            'nascimento.before' => 'Confira a data de nascimento.',
            'melhor_dia.max' => 'Escolha um dia entre 1 e 28, que existe em todo mês.',
            'aceite.required' => 'É preciso aceitar os termos para continuar.',
        ]);

        $pedido = $abrir($oferta, [
            'nome' => $dados['nome'],
            'documento' => $dados['documento'],
            'email' => $dados['email'],
            'telefone' => $dados['telefone'],
            'nascimento' => \Carbon\Carbon::parse($dados['nascimento']),
            'melhor_dia' => (int) $dados['melhor_dia'],
            'endereco' => sprintf(
                '%s, %s - %s - %s/%s - CEP %s',
                $dados['logradouro'], $dados['numero'], $dados['bairro'],
                $dados['cidade'], mb_strtoupper($dados['uf']), $dados['cep'],
            ),
        ]);

        if ($pedido->situacao === 'reprovado') {
            // O motivo tecnico fica no pedido, para auditoria; a tela diz o
            // suficiente para a pessoa entender e nao mais que isso. Detalhar
            // a regra aqui ensina quem quiser contorna-la.
            return redirect()->route('checkout.resultado', $pedido);
        }

        $pedido->update(['contrato_assinado_em' => now(), 'situacao' => 'aguardando_entrada']);

        $entrada = $pedido->entrada();

        // Oferta sem entrada nunca deveria existir, e a validacao da oferta
        // impede. Se chegar aqui assim mesmo, o pedido fica sem parcela zero e
        // `podeParcelar()` jamais fica verdadeiro: o carne nunca nasceria e
        // nada na tela diria o porque. Melhor o erro alto agora.
        if ($entrada === null) {
            Log::error('Pedido do 360 sem parcela de entrada', ['pedido' => $pedido->id]);

            abort(422, 'Esta oferta está com a entrada mal configurada. Fale com quem vendeu.');
        }

        try {
            $emitir($entrada);
        } catch (\Throwable $erro) {
            // A venda esta gravada e o contrato aceito: boleto que nao saiu se
            // emite de novo, e perder o pedido inteiro por indisponibilidade
            // do provedor seria jogar fora uma venda ja fechada.
            Log::warning('Entrada do 360 sem cobranca emitida', ['pedido' => $pedido->id, 'erro' => $erro->getMessage()]);
        }

        return redirect()->route('checkout.resultado', $pedido);
    }

    /**
     * O que aconteceu com a proposta, com o boleto da entrada quando houver.
     *
     * A pagina e alcancavel por quem tem o id do pedido. Ela mostra valor,
     * parcelamento e link de pagamento, e nunca o documento inteiro de quem
     * comprou: e endereco que se compartilha por engano.
     */
    public function resultado(Pedido360 $pedido)
    {
        return view('paginas.checkout-resultado', [
            'pedido' => $pedido->load('oferta.produto.produtor', 'parcelas'),
            'entrada' => $pedido->entrada(),
        ]);
    }
}
