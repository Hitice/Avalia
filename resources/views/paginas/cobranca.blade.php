@extends('layouts.fullscreen-layout', ['title' => 'Avalia 360'])

@php
    use App\Support\Empresa;
    use App\Support\Suporte;

    // As perguntas que o produtor faz antes de qualquer outra, na ordem em que
    // ele as faz. Vivem aqui, e nao soltas no markup, para a lista nao crescer
    // com pergunta repetida escrita de outro jeito.
    $duvidas = [
        [
            'pergunta' => 'Quem assume o risco se o cliente não pagar?',
            'resposta' => 'O risco é da sua operação, como em qualquer venda parcelada própria. O que o Avalia 360 faz é reduzir esse risco antes da venda, com a análise que a Avalia One já opera, e cuidar da régua de cobrança depois dela.',
        ],
        [
            'pergunta' => 'Em quanto tempo o dinheiro entra?',
            'resposta' => 'Cada parcela cai na sua conta conforme o cliente paga, no prazo do meio de pagamento: Pix no mesmo dia, boleto em um a dois dias úteis após a compensação.',
        ],
        [
            'pergunta' => 'Preciso trocar meu sistema de vendas?',
            'resposta' => 'Não. O checkout do Avalia 360 é um link. Você o envia como já envia o link de pagamento que usa hoje.',
        ],
        [
            'pergunta' => 'Qual é o custo?',
            'resposta' => 'Uma taxa por venda efetivada, combinada no cadastro, sem mensalidade e sem cobrança por proposta recusada. O valor sai do repasse, então não há boleto para você pagar.',
        ],
        [
            'pergunta' => 'Meu cliente precisa de cartão de crédito?',
            'resposta' => 'Não, e esse é o ponto. O parcelamento sai em boleto ou Pix, o que alcança quem não tem limite no cartão ou não quer comprometê-lo.',
        ],
    ];
@endphp

@section('content')
    <div class="min-h-screen bg-white text-gray-800 dark:bg-gray-900 dark:text-white/90"
         x-data="{ duvida: null, formulario: false }"
         x-init="@if (session('cobranca_ok') || $errors->any()) formulario = true @endif"
         @keydown.escape.window="formulario = false">

        <header class="fixed inset-x-0 top-0 z-40 border-b border-gray-200 bg-white/95 shadow-theme-md backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
            <div class="mx-auto flex h-[60px] w-full max-w-[87rem] items-center justify-between px-6">
                <a href="{{ route('inicio') }}" aria-label="Início" class="flex items-center gap-3">
                    <x-avalia.logotipo :tamanho="34" texto="1.3rem" />
                    {{-- A etiqueta e o que separa o produto da marca: a casa e
                         Avalia One, e 360 e o nome da estrutura de cobranca. --}}
                    <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
                </a>

                {{-- O acesso mora na caixa do alto, entao o topo nao repete
                     os mesmos dois botoes. O que falta aqui e o caminho de
                     volta: o 360 e uma parte da Avalia One, e quem entrou por
                     este endereco precisa achar a porta de casa. --}}
                <nav class="flex items-center gap-3">
                    <x-avalia.tema />

                    <a href="{{ route('inicio') }}" class="botao botao-secundario">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5 5-5M18 12H6"/>
                        </svg>
                        Voltar
                    </a>
                </nav>
            </div>
        </header>

        <main class="pt-[60px]">
            {{-- Abertura. A promessa e a venda que hoje nao acontece, e nao a
                 tecnologia: quem vende curso ou serviço perde negocio no "não
                 tenho limite no cartão", e e esse o problema que se resolve. --}}
            <section class="mx-auto grid w-full max-w-[87rem] items-center gap-12 px-6 py-16 sm:py-24 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="max-w-2xl">
                    {{-- A marca no lugar do rotulo escrito: o 360 se apresenta
                         pelo proprio logotipo, e nao por uma etiqueta que
                         repete em texto o que a marca ja diz. --}}
                    <span class="inline-flex items-center gap-2.5">
                        <x-avalia.logotipo :tamanho="36" texto="1.35rem" />
                        <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
                    </span>

                    <h1 class="mt-5 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                        Parcele no boleto e no Pix<br>
                        <span class="text-brand-500">sem depender do cartão.</span>
                    </h1>

                    <p class="mt-5 max-w-2xl text-lg text-gray-500 dark:text-gray-400">
                        Seu cliente fecha em até doze vezes mesmo sem limite no cartão.
                        Você acompanha cada parcela, e a régua de cobrança é nossa.
                    </p>

                    {{-- A caixa de acesso fica no alto, junto da promessa:
                         quem ja e produtor volta aqui todo dia para ver o que
                         caiu, e obrigar essa pessoa a procurar um botao Entrar
                         cobra um clique de quem ja decidiu.

                         Mesmo texto e mesma ordem da entrada do CRM: quem usa
                         os dois lados da casa nao deveria ter que reaprender a
                         entrar. --}}
                    <form method="POST" action="{{ route('produtor.entrar.enviar') }}"
                          class="cartao mt-8 max-w-md p-6">
                        @csrf

                        <h2 class="text-lg font-semibold tracking-tight">Bem-vindo de volta</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Entre com seu e-mail e senha.</p>

                        @if ($errors->acessoProdutor->any())
                            <div class="aviso aviso-erro mt-4">{{ $errors->acessoProdutor->first() }}</div>
                        @endif

                        <div class="mt-5 grid gap-4">
                            <div>
                                <label for="acesso_email" class="rotulo-campo">E-mail *</label>
                                <input id="acesso_email" name="email" type="email" class="campo" required
                                       autocomplete="username" value="{{ old('email') }}">
                            </div>
                            <div>
                                <label for="acesso_senha" class="rotulo-campo">Senha *</label>
                                <input id="acesso_senha" name="senha" type="password" class="campo" required
                                       autocomplete="current-password">
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <input type="checkbox" name="lembrar" value="1"
                                       class="size-4 rounded border-gray-300 accent-brand-500 dark:border-gray-600">
                                Manter conectado
                            </label>

                            <a href="{{ route('senha.esqueci') }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">
                                Esqueci minha senha
                            </a>
                        </div>

                        <button type="submit" class="botao botao-primario mt-5 w-full">Entrar</button>

                        <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            Novo por aqui?
                            <button type="button" @click="formulario = true"
                                    class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                Solicite seu cadastro.
                            </button>
                        </p>
                    </form>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <button type="button" @click="formulario = true" class="botao botao-secundario">
                            Falar com a equipe
                        </button>
                    </div>

                    <ul class="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500 dark:text-gray-400">
                        @foreach (['Sem mensalidade', 'Taxa só na venda efetivada', 'Análise antes de aprovar'] as $item)
                            <li class="flex items-center gap-2">
                                <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- A foto e recortada e fica direto sobre o fundo da
                     pagina, que ja e branco no tema claro e escuro no outro.
                     Tinha uma mancha circular atras, e ela competia com o
                     recorte em vez de ajuda-lo.

                     Escondida no telefone de proposito. Ali a coluna vira uma
                     so, e uma foto de 740px de altura empurraria o formulario
                     para fora da primeira dobra sem dizer nada que o texto ja
                     nao diga. --}}
                {{-- Transparencia de verdade agora, entao a foto vale nos dois
                     temas: ela fica direto sobre o fundo da pagina, branco num
                     e escuro no outro.

                     Escondida no telefone de proposito. Ali a coluna vira uma
                     so, e 710px de foto empurrariam a caixa de acesso para
                     fora da primeira dobra sem dizer nada que o texto ja nao
                     diga. --}}
                <div class="hidden lg:block">
                    <img src="{{ asset('images/business1.png') }}" width="529" height="710" decoding="async"
                         alt="Dois profissionais atendendo clientes"
                         class="mx-auto w-full max-w-lg">
                </div>
            </section>

            <section class="border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                <div class="mx-auto w-full max-w-[87rem] px-6 py-16">
                    <h2 class="text-2xl font-semibold tracking-tight">Como funciona</h2>

                    <div class="mt-8 grid gap-6 md:grid-cols-3">
                        @foreach ([
                            ['n' => '1', 'titulo' => 'Você cria a oferta', 'texto' => 'Informa o valor, em quantas vezes aceita parcelar e o quanto quer de entrada. Sai um link de checkout.'],
                            ['n' => '2', 'titulo' => 'O cliente compra', 'texto' => 'Ele preenche os dados, passa pela análise, assina o contrato e paga a entrada em Pix ou boleto.'],
                            ['n' => '3', 'titulo' => 'As parcelas rodam', 'texto' => 'Emitimos os boletos seguintes no dia que o cliente escolheu, cobramos os atrasos e repassamos o que entra.'],
                        ] as $passo)
                            <div class="cartao p-6">
                                <span class="flex size-9 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">{{ $passo['n'] }}</span>
                                <h3 class="mt-4 font-semibold">{{ $passo['titulo'] }}</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $passo['texto'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- O que a casa opera pelo produtor.

                 Cada item aqui e uma coisa que o sistema faz hoje. Promessa de
                 funcionalidade que ainda nao existe vira reclamacao na primeira
                 semana, e quem atende e o mesmo time que vendeu. --}}
            <section class="mx-auto w-full max-w-[87rem] px-6 py-16">
                <div class="max-w-3xl">
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Você vende. A cobrança fica com a gente.
                    </h2>
                    <p class="mt-4 text-gray-500 dark:text-gray-400">
                        Parcelar no boleto dá trabalho depois da venda: alguém precisa analisar quem
                        compra, emitir os títulos todo mês, lembrar quem esqueceu e cobrar quem
                        atrasou. É esse trabalho que a Avalia 360 assume.
                    </p>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Análise antes de aprovar', 'Cada proposta passa pela mesma régua que a Avalia One usa em consulta de crédito: documento conferido, histórico e limite por comprador.'],
                        ['Contrato assinado', 'A venda só vira carnê depois do aceite registrado, com data e condições guardadas. Cobrança sem contrato é discussão na hora do atraso.'],
                        ['Boletos emitidos no prazo', 'A entrada sai na hora da compra e as parcelas no dia que o cliente escolheu. Você não precisa lembrar de emitir nada.'],
                        ['Cobrança de quem atrasa', 'Régua de lembrete antes e depois do vencimento. A negativação existe, e só acontece quando você manda.'],
                        ['Repasse direto na sua conta', 'O dinheiro cai na sua conta no provedor, não na nossa. A taxa sai da parcela, e você vê a composição de cada real.'],
                        ['Painel com o que falta receber', 'Vendas, parcelas em aberto, atrasos e o que já entrou. Os números saem do extrato, não de uma coluna que alguém atualiza.'],
                    ] as [$titulo, $texto])
                        <div class="cartao p-6">
                            <h3 class="font-semibold">{{ $titulo }}</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $texto }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- O argumento comercial, em efeitos verificaveis. Nenhum numero
                 inventado de conversao: prometer porcentagem que ninguem mediu
                 aqui e o tipo de frase que o cliente cobra depois. --}}
            <section class="border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                <div class="mx-auto w-full max-w-[87rem] px-6 py-16">
                    <div class="grid gap-10 lg:grid-cols-2">
                        <div>
                            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                                O que muda quando o boleto parcela
                            </h2>
                            <p class="mt-4 text-gray-500 dark:text-gray-400">
                                Metade do país não tem limite de cartão para uma compra de três mil reais,
                                e boa parte dela tem renda para pagar em doze vezes. O parcelamento no
                                boleto alcança esse comprador sem mudar o seu preço.
                            </p>

                            <button type="button" @click="formulario = true" class="botao botao-primario mt-8">
                                Quero ampliar meu faturamento
                            </button>
                        </div>

                        <ul class="grid gap-4 sm:grid-cols-2">
                            @foreach ([
                                'Alcança quem não tem limite no cartão',
                                'Permite ticket maior sem assustar o comprador',
                                'Menos gente desiste no meio do checkout',
                                'Sua venda deixa de depender da bandeira',
                                'Entrada e parcelas com datas que você conhece',
                                'O preço do seu produto continua o mesmo',
                            ] as $efeito)
                                <li class="cartao flex items-start gap-3 p-5">
                                    <svg class="mt-0.5 size-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    <span class="text-sm">{{ $efeito }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>

            {{-- Por que esta casa, e nao outra. O argumento e o que ja existe
                 aqui: a Avalia One opera consulta de credito desde antes do
                 360, com contrato de bureau e regra de retencao. --}}
            <section class="mx-auto w-full max-w-[87rem] px-6 py-16">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Por que a Avalia 360</h2>

                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    @foreach ([
                        ['Crédito é o que a casa faz', 'A Avalia One já opera consulta de score e restrição para empresas que vendem a prazo. O 360 usa a mesma base para dizer quem pode parcelar.'],
                        ['O dinheiro não passa por nós', 'O repasse vai direto para a sua conta no provedor de pagamento, em cada parcela paga. Nós ficamos com a taxa, e ela aparece discriminada.'],
                        ['Você enxerga cada centavo', 'Toda parcela tem valor bruto, taxa do provedor, taxa da plataforma e repasse registrados. A conta fecha, e dá para conferir uma a uma.'],
                    ] as [$titulo, $texto])
                        <div class="cartao p-6">
                            <h3 class="font-semibold">{{ $titulo }}</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $texto }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto w-full max-w-[87rem] px-6 py-16">
                <div class="grid gap-10 lg:grid-cols-2">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">Para quem é</h2>
                        <ul class="mt-6 space-y-4">
                            @foreach ([
                                ['Produtor de curso e mentoria', 'O aluno que não tem limite no cartão para um curso de R$ 3 mil costuma ter renda para pagá-lo em doze vezes.'],
                                ['Prestador de serviço recorrente', 'Clínica, escritório, assistência técnica: serviço fechado hoje, pago ao longo dos meses, sem antecipadora no meio.'],
                                ['Quem já vende parcelado no caderno', 'Você já parcela na confiança. Aqui a análise vem antes, o contrato existe, e a cobrança não é você quem faz.'],
                            ] as [$titulo, $texto])
                                <li class="flex gap-3">
                                    <svg class="mt-0.5 size-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    <span>
                                        <strong class="font-semibold">{{ $titulo }}</strong>
                                        <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">{{ $texto }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">Perguntas frequentes</h2>

                        {{-- Uma aberta por vez. Accordion e proposital: a lista
                             inteira aberta vira parede de texto, e quem chega
                             ate aqui tem uma duvida especifica, nao cinco. --}}
                        <div class="mt-6 divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($duvidas as $i => $duvida)
                                <div>
                                    <button type="button" class="flex w-full items-center justify-between gap-4 py-4 text-left font-medium"
                                            @click="duvida = duvida === {{ $i }} ? null : {{ $i }}"
                                            :aria-expanded="duvida === {{ $i }}">
                                        {{ $duvida['pergunta'] }}
                                        <svg class="size-5 shrink-0 text-gray-400 transition" :class="duvida === {{ $i }} && 'rotate-180'"
                                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                                        </svg>
                                    </button>
                                    <p x-show="duvida === {{ $i }}" x-transition.opacity.duration.200ms class="pb-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $duvida['resposta'] }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

        </main>

        {{-- O formulario vive num overlay, e nao no corpo da pagina.

             Como secao, ele ocupava uma dobra inteira que so interessa a quem
             ja decidiu falar com alguem, e empurrava as duvidas para baixo. Em
             overlay, quem quer preencher chama, e quem esta lendo continua
             lendo. Esc, clique fora e o X fecham. --}}
        <div x-cloak x-show="formulario" x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/60 p-4 py-10 backdrop-blur-sm"
             @click.self="formulario = false" role="dialog" aria-modal="true" aria-label="Fale com a Avalia 360">
            <div class="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white px-6 py-6 shadow-theme-lg sm:px-8 dark:border-gray-700 dark:bg-gray-800">
                <button type="button" @click="formulario = false" aria-label="Fechar"
                        class="absolute top-4 right-4 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>

                <h2 class="text-xl font-semibold tracking-tight">Fale com a Avalia 360</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Preencha abaixo e um especialista entra em contato pelo WhatsApp informado,
                    em horário comercial.
                </p>

                @if (session('cobranca_ok'))
                    <div class="aviso aviso-ok mt-5">
                        Pedido recebido. A equipe do Avalia 360 entra em contato pelo WhatsApp informado.
                    </div>
                @endif

                <form method="POST" action="{{ route('cobranca.pre-cadastro') }}" class="mt-5 grid gap-4">
                    @csrf

                    {{-- A isca. Fora da vista e sem rotulo alcancavel: pessoa
                         nao ve, robo preenche tudo. --}}
                    <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                    <div>
                        <label for="nome" class="rotulo-campo">Nome completo</label>
                        <input id="nome" name="nome" type="text" class="campo" required value="{{ old('nome') }}">
                        @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="whatsapp" class="rotulo-campo">DDD + WhatsApp</label>
                            <input id="whatsapp" name="whatsapp" type="text" class="campo" required
                                   placeholder="(34) 99999-9999" value="{{ old('whatsapp') }}">
                            @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="instagram" class="rotulo-campo">Seu @ no Instagram</label>
                            <input id="instagram" name="instagram" type="text" class="campo" required
                                   placeholder="@seunegocio" value="{{ old('instagram') }}">
                            @error('instagram') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="rotulo-campo">E-mail</label>
                        <input id="email" name="email" type="email" class="campo" required value="{{ old('email') }}">
                        @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="vende" class="rotulo-campo">O que você vende?</label>
                        <select id="vende" name="vende" class="campo" required>
                            <option value="">Escolha uma opção</option>
                            @foreach ($vende as $opcao)
                                <option value="{{ $opcao }}" @selected(old('vende') === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                        @error('vende') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="papel" class="rotulo-campo">Qual o seu papel no negócio?</label>
                        <select id="papel" name="papel" class="campo" required>
                            <option value="">Escolha uma opção</option>
                            @foreach ($papeis as $opcao)
                                <option value="{{ $opcao }}" @selected(old('papel') === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                        @error('papel') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="prazo" class="rotulo-campo">Quando quer começar a parcelar?</label>
                        <select id="prazo" name="prazo" class="campo">
                            <option value="">Prefiro não dizer agora</option>
                            @foreach ($prazos as $opcao)
                                <option value="{{ $opcao }}" @selected(old('prazo') === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="faturamento_ano" class="rotulo-campo">Faturamento no último ano</label>
                        <select id="faturamento_ano" name="faturamento_ano" class="campo">
                            <option value="">Prefiro não informar</option>
                            @foreach ($faturamentos as $opcao)
                                <option value="{{ $opcao }}" @selected(old('faturamento_ano') === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="botao botao-primario mt-1 w-full">Enviar</button>

                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Seus dados ficam só com a Avalia One e servem apenas para este contato.
                    </p>
                </form>
            </div>
        </div>


        <footer class="border-t border-gray-100 dark:border-gray-800">
            <div class="mx-auto flex w-full max-w-[87rem] flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('inicio') }}" aria-label="Início">
                    <x-avalia.logotipo :tamanho="24" />
                </a>
                <p class="text-center text-xs leading-relaxed text-gray-400 dark:text-gray-500">
                    © {{ now()->year }} {{ Empresa::razaoSocial() }} · CNPJ {{ Empresa::cnpj() }}<br>
                    {{ Empresa::localidade() }}
                </p>
                <a class="hover:text-brand-500" href="mailto:{{ Empresa::email() }}">{{ Empresa::email() }}</a>
            </div>
        </footer>
    </div>
@endsection
