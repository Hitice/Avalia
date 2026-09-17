<?php

namespace App\Http\Controllers;

use App\Mail\PreCadastroRecebido;
use App\Models\InteressadoCobranca;
use App\Support\Dinheiro;
use App\Support\Documento;
use App\Support\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * A pagina do Avalia 360 e o pre-cadastro de quem quer vender parcelado.
 *
 * O Avalia 360 e a estrutura de cobranca da Avalia One: o produtor vende
 * parcelado no boleto e no Pix, e quem opera a regua de cobranca somos nos.
 *
 * A validacao fica aqui, e nao num FormRequest, pelo mesmo motivo do
 * InteresseController: o campo-isca precisa responder ANTES de validar. Num
 * FormRequest, robo que preenche a isca e erra os outros campos receberia a
 * lista de erros de volta, que e exatamente a aula que nao queremos dar.
 */
class CobrancaController extends Controller
{
    /** Faixas de faturamento. Texto fechado: e resposta de formulario, nao conta. */
    public const VOLUMES = [
        'Até R$ 10 mil',
        'R$ 10 mil a R$ 50 mil',
        'R$ 50 mil a R$ 200 mil',
        'Mais de R$ 200 mil',
    ];

    public function mostrar()
    {
        return view('paginas.cobranca', ['volumes' => self::VOLUMES]);
    }

    public function preCadastro(Request $pedido)
    {
        // Campo que nenhuma pessoa ve nem preenche. Finge sucesso e descarta:
        // responder "detectei voce" e ensinar o robo a melhorar.
        if ($pedido->filled('site')) {
            return back()->with('cobranca_ok', true);
        }

        $pedido->merge([
            'documento' => Documento::normalizarCnpj($pedido->input('documento')),
            'email' => mb_strtolower(trim((string) $pedido->input('email'))),
            'whatsapp' => preg_replace('/\D/', '', (string) $pedido->input('whatsapp')) ?? '',
        ]);

        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'min:3', 'max:120'],
            'documento' => [
                'required', 'string',
                fn ($atributo, $valor, $falhou) => Documento::documentoValido($valor)
                    ? null
                    : $falhou('Confira o CPF ou o CNPJ: os dígitos não fecham.'),
            ],
            // Unico de verdade no banco. A mensagem nao diz "ja existe" por
            // acaso: confirmar cadastro a quem digita e-mail alheio entrega
            // quem e cliente nosso.
            'email' => ['required', 'email', 'max:150', Rule::unique('interessados_cobranca', 'email')],
            'whatsapp' => ['required', 'string', 'min:10', 'max:11'],
            'ticket_medio' => ['required', 'string', 'max:30'],
            'volume_mensal' => ['required', Rule::in(self::VOLUMES)],
        ], [
            'nome.required' => 'Diga como podemos te chamar.',
            'documento.required' => 'Informe o CPF ou o CNPJ.',
            'email.email' => 'Confira o e-mail informado.',
            'email.unique' => 'Já recebemos um pedido com este e-mail. Em breve falamos com você.',
            'whatsapp.required' => 'Informe o WhatsApp com DDD.',
            'whatsapp.min' => 'Informe o WhatsApp com DDD.',
            'ticket_medio.required' => 'Informe o ticket médio.',
            'volume_mensal.required' => 'Escolha uma faixa.',
        ]);

        $centavos = Dinheiro::paraCentavos($dados['ticket_medio']);

        if ($centavos === null || $centavos < 1) {
            return back()->withInput()->withErrors(['ticket_medio' => 'Informe o ticket médio em reais.']);
        }

        $interessado = InteressadoCobranca::create([
            'nome' => $dados['nome'],
            'documento' => $dados['documento'],
            'email' => $dados['email'],
            'whatsapp' => $dados['whatsapp'],
            'ticket_medio_cents' => $centavos,
            'volume_mensal' => $dados['volume_mensal'],
        ]);

        // O pedido ja esta no banco: e-mail que nao sai nao pode derrubar a
        // resposta nem perder o lead. Registra sem dado pessoal e segue.
        try {
            Mail::to(Empresa::email())->send(new PreCadastroRecebido($interessado));
        } catch (\Throwable $erro) {
            Log::warning('Aviso de pre-cadastro nao enviado', [
                'interessado' => $interessado->id,
                'erro' => $erro->getMessage(),
            ]);
        }

        return back()->with('cobranca_ok', true);
    }
}
