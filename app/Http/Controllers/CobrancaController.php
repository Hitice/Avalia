<?php

namespace App\Http\Controllers;

use App\Mail\PreCadastroRecebido;
use App\Models\InteressadoCobranca;
use App\Support\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * A pagina do Avalia Gestor e o pre-cadastro de quem quer vender parcelado.
 *
 * O Avalia Gestor e a estrutura de cobranca da Avalia One: o produtor vende
 * parcelado no boleto e no Pix, e quem opera a regua de cobranca somos nos.
 *
 * A validacao fica aqui, e nao num FormRequest, pelo mesmo motivo do
 * InteresseController: o campo-isca precisa responder ANTES de validar. Num
 * FormRequest, robo que preenche a isca e erra os outros campos receberia a
 * lista de erros de volta, que e exatamente a aula que nao queremos dar.
 */
class CobrancaController extends Controller
{
    /*
     * As respostas possiveis de cada pergunta.
     *
     * Listas fechadas, e nao campo livre: sao respostas de formulario que a
     * equipe vai ler em lote para decidir a quem ligar primeiro, e campo livre
     * vira quarenta jeitos de escrever a mesma coisa.
     */
    public const VENDE = [
        'Cursos online',
        'Mentorias',
        'Imersões e eventos',
        'Serviços',
        'Outro',
    ];

    public const PAPEIS = [
        'Sou o dono do negócio',
        'Sócio',
        'Gestor ou equipe',
        'Represento o produtor',
    ];

    public const PRAZOS = [
        'Quero começar agora',
        'Nos próximos 30 dias',
        'Nos próximos 3 meses',
        'Só pesquisando',
    ];

    public const FATURAMENTOS = [
        'Até R$ 100 mil',
        'R$ 100 mil a R$ 500 mil',
        'R$ 500 mil a R$ 2 milhões',
        'Mais de R$ 2 milhões',
        'Prefiro não informar',
    ];

    public function mostrar()
    {
        return view('paginas.cobranca', [
            'vende' => self::VENDE,
            'papeis' => self::PAPEIS,
            'prazos' => self::PRAZOS,
            'faturamentos' => self::FATURAMENTOS,
        ]);
    }

    public function preCadastro(Request $pedido)
    {
        // Campo que nenhuma pessoa ve nem preenche. Finge sucesso e descarta:
        // responder "detectei voce" e ensinar o robo a melhorar.
        if ($pedido->filled('site')) {
            return back()->with('cobranca_ok', true);
        }

        $pedido->merge([
            'email' => mb_strtolower(trim((string) $pedido->input('email'))),
            'whatsapp' => preg_replace('/\D/', '', (string) $pedido->input('whatsapp')) ?? '',
        ]);

        // Nao se pede documento aqui. Quem preenche este formulario ainda
        // esta decidindo, e CPF antes de falar com alguem e o campo que mais
        // faz gente desistir no meio. O documento vem no cadastro da conta,
        // quando ja existe interesse de verdade.
        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'min:3', 'max:120'],
            // Unico de verdade no banco. A mensagem nao diz "ja existe" por
            // acaso: confirmar cadastro a quem digita e-mail alheio entrega
            // quem e cliente nosso.
            'email' => ['required', 'email', 'max:150', Rule::unique('interessados_cobranca', 'email')],
            'whatsapp' => ['required', 'string', 'min:10', 'max:11'],
            'instagram' => ['required', 'string', 'max:60'],
            'vende' => ['required', Rule::in(self::VENDE)],
            'papel' => ['required', Rule::in(self::PAPEIS)],
            'prazo' => ['nullable', Rule::in(self::PRAZOS)],
            'faturamento_ano' => ['nullable', Rule::in(self::FATURAMENTOS)],
        ], [
            'nome.required' => 'Diga como podemos te chamar.',
            'email.email' => 'Confira o e-mail informado.',
            'email.unique' => 'Já recebemos um pedido com este e-mail. Em breve falamos com você.',
            'whatsapp.required' => 'Informe o WhatsApp com DDD.',
            'whatsapp.min' => 'Informe o WhatsApp com DDD.',
            'instagram.required' => 'Informe o @ do seu Instagram.',
            'vende.required' => 'Escolha o que você vende.',
            'papel.required' => 'Escolha o seu papel no negócio.',
        ]);

        $interessado = InteressadoCobranca::create([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'whatsapp' => $dados['whatsapp'],
            // Com ou sem arroba, guardado sempre do mesmo jeito: a equipe
            // procura por @fulano e por fulano, e as duas tem que achar.
            'instagram' => '@'.ltrim(trim($dados['instagram']), '@'),
            'vende' => $dados['vende'],
            'papel' => $dados['papel'],
            'prazo' => $dados['prazo'] ?? null,
            'faturamento_ano' => $dados['faturamento_ano'] ?? null,
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
