@extends('layouts.fullscreen-layout', ['title' => 'Avalia 360'])

@php
    use App\Support\Empresa;

    // Cada bloco tem resumo e detalhe. O resumo cabe no cartao, o detalhe
    // abre no popup: quem esta passando o olho le seis linhas, e quem se
    // interessou por uma delas le o paragrafo inteiro sem sair da pagina.
    $operacao = [
        ['Análise antes de aprovar', 'M9 12l2 2 4-4M7.8 4.7a3.3 3.3 0 0 0 1.9-.8 3.3 3.3 0 0 1 4.6 0 3.3 3.3 0 0 0 1.9.8 3.3 3.3 0 0 1 3.1 3.1 3.3 3.3 0 0 0 .8 1.9 3.3 3.3 0 0 1 0 4.6 3.3 3.3 0 0 0-.8 1.9 3.3 3.3 0 0 1-3.1 3.1 3.3 3.3 0 0 0-1.9.8 3.3 3.3 0 0 1-4.6 0 3.3 3.3 0 0 0-1.9-.8 3.3 3.3 0 0 1-3.1-3.1 3.3 3.3 0 0 0-.8-1.9 3.3 3.3 0 0 1 0-4.6 3.3 3.3 0 0 0 .8-1.9 3.3 3.3 0 0 1 3.1-3.1z',
         'Cada proposta passa pela mesma régua que a Avalia One usa em consulta de crédito.',
         'Documento conferido dígito a dígito, idade mínima, uma compra em aberto por comprador e a checagem de restrição. A decisão fica gravada com a versão da regra que decidiu, então dá para explicar uma recusa de seis meses atrás mesmo depois de a régua mudar.'],

        ['Cobrança de quem atrasa', 'M12 8v5l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
         'Régua de lembrete antes e depois do vencimento, com negativação sob seu comando.',
         'O vencimento não vem do nosso calendário: vem do provedor dizendo que o prazo passou sem pagamento. A negativação existe e nunca é automática. Ela acontece quando você manda, porque a relação com o cliente é sua.'],

        ['Repasse e painel', 'M3 17l5-5 4 4 8-9M16 7h5v5',
         'O dinheiro cai na sua conta no provedor, e o painel mostra o que entrou e o que falta.',
         'Cada cobrança sai com a divisão já feita: a sua parte vai para a sua carteira no momento em que o cliente paga. Toda parcela registra valor bruto, taxa do provedor, taxa da plataforma e repasse, e a soma tem que fechar. Quando não fecha, o sistema acusa.'],
    ];

    $perfis = [
        ['Produtor de curso e mentoria', 'M12 14l9-5-9-5-9 5 9 5zm0 0v7m-5-9.2V17c0 1.1 2.2 2 5 2s5-.9 5-2v-5.2', 'O aluno sem limite no cartão para um curso de R$ 3 mil costuma ter renda para pagá-lo em 12x.',
         'É a venda que você perde hoje sem saber: a pessoa quer, tem como pagar ao longo do ano, e trava na hora de comprometer o limite. O boleto parcelado alcança esse comprador sem mudar o preço do seu produto.'],
        ['Prestador de serviço recorrente', 'M21 13.3A17.9 17.9 0 0110.7 3 2 2 0 019 1H6a2 2 0 00-2 2c0 9.4 7.6 17 17 17a2 2 0 002-2v-3a2 2 0 00-2-2z', 'Clínica, escritório, assistência técnica: serviço fechado hoje, pago ao longo dos meses.',
         'Sem antecipadora no meio e sem depender da máquina de cartão. O serviço é combinado uma vez, o contrato registra as condições, e a cobrança das parcelas roda sozinha enquanto você atende.'],
        ['Quem já vende parcelado no caderno', 'M12 6.3a7.5 7.5 0 00-5-1.8c-1.8 0-3.5.5-5 1.4v13a11 11 0 015-1.2c1.8 0 3.5.5 5 1.3m0-12.7a7.5 7.5 0 015-1.8c1.8 0 3.5.5 5 1.4v13a11 11 0 00-5-1.2c-1.8 0-3.5.5-5 1.3m0-12.7V19', 'Você já parcela na confiança. Aqui a análise vem antes e a cobrança não é você quem faz.',
         'A diferença entre parcelar no caderno e parcelar com estrutura é o que acontece quando alguém para de pagar. Aqui existe contrato, existe régua de cobrança e existe registro de tudo que foi combinado.'],
    ];
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
    ];
@endphp

@section('content')
    <div class="min-h-screen bg-white text-gray-800 dark:bg-gray-900 dark:text-white/90"
         x-data="{ duvida: null, formulario: false, detalhe: null }"
         x-init="@if (session('cobranca_ok') || $errors->any()) formulario = true @endif"
         @keydown.escape.window="formulario = false; detalhe = null">

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
                <nav class="flex items-center gap-3 pr-12 sm:pr-14 min-[1550px]:pr-0">
                    <a href="{{ route('inicio') }}" class="botao botao-secundario">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5 5-5M18 12H6"/>
                        </svg>
                        Voltar
                    </a>
                </nav>
            </div>

            {{-- Na ponta extrema, fora do alinhamento das colunas, como na
                 pagina inicial: e ferramenta da pagina, e nao passo do funil.
                 O respiro a direita do nav reserva o lugar dele. --}}
            <x-avalia.tema class="absolute top-1/2 right-3 size-11 -translate-y-1/2 sm:right-4" />
        </header>

        <main class="pt-[60px]">
            {{-- Abertura. A promessa e a venda que hoje nao acontece, e nao a
                 tecnologia: quem vende curso ou serviço perde negocio no "não
                 tenho limite no cartão", e e esse o problema que se resolve. --}}
            <section class="mx-auto grid w-full max-w-[87rem] items-center gap-12 px-6 py-16 sm:py-24 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="max-w-2xl">
                    {{-- Sem marca aqui. Ela esta no topo, a poucos pixels de
                         distancia, e repeti-la logo abaixo gasta a primeira
                         linha da pagina dizendo de novo onde a pessoa esta, em
                         vez de dizer o que ela ganha. --}}
                    <h1 class="entra-suave text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                        Parcele no boleto e no Pix<br>
                        <span class="text-brand-500">sem depender do cartão.</span>
                    </h1>

                    <p class="entra-suave mt-5 max-w-2xl text-lg text-gray-500 dark:text-gray-400" style="animation-delay: 0.1s">
                        Seu cliente paga em até 12x mesmo sem limite no cartão.
                        Você acompanha cada parcela, e nós gerenciamos a cobrança.
                    </p>

                    {{-- A caixa de acesso fica no alto, junto da promessa:
                         quem ja e produtor volta aqui todo dia para ver o que
                         caiu, e obrigar essa pessoa a procurar um botao Entrar
                         cobra um clique de quem ja decidiu.

                         Mesmo texto e mesma ordem da entrada do CRM: quem usa
                         os dois lados da casa nao deveria ter que reaprender a
                         entrar. --}}
                    <form method="POST" action="{{ route('produtor.entrar.enviar') }}"
                          class="entra-suave cartao mt-8 max-w-md p-6" style="animation-delay: 0.2s">
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

                    <div class="entra-suave mt-6 flex flex-wrap items-center gap-3" style="animation-delay: 0.3s">
                        <button type="button" @click="formulario = true" class="botao botao-secundario">
                            Falar com a equipe
                        </button>
                    </div>

                    <ul class="entra-suave mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500 dark:text-gray-400" style="animation-delay: 0.4s">
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

                    <div class="grid gap-6 md:grid-cols-3">
                        @foreach ([
                            ['n' => '1', 'titulo' => 'Você cria a oferta', 'texto' => 'Informa o valor, a quantidade de parcelas e o valor de entrada. Com isso, um link de checkout personalizado é gerado para o cliente.'],
                            ['n' => '2', 'titulo' => 'O cliente recebe', 'texto' => 'Preenche os dados, passa pela análise, assina o contrato e paga a entrada no Pix ou boleto.'],
                            ['n' => '3', 'titulo' => 'Você recebe', 'texto' => 'Emitimos os boletos na data estipulada, realizamos a cobrança e o pagamento entra na sua conta.'],
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
                <div class="mb-8 max-w-3xl">
                    <span class="text-sm font-medium tracking-wide text-brand-500 uppercase dark:text-brand-400">
                        Operação
                    </span>
                    <h2 class="mt-1 text-3xl font-semibold text-gray-800 dark:text-white">
                        Você vende. A cobrança fica com a gente.
                    </h2>
                    <p class="mt-4 text-gray-500 dark:text-gray-400">
                        Parcelar no boleto dá trabalho depois da venda: analisar quem compra, emitir
                        os títulos todo mês e cobrar quem atrasou. A Avalia 360 assume esse trabalho,
                        com a estrutura de crédito que a Avalia One já opera há anos.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($operacao as [$titulo, $icone, $resumo, $detalhe])
                        <button type="button" @click="detalhe = { titulo: @js($titulo), texto: @js($detalhe), icone: @js($icone) }"
                                class="group cartao cursor-pointer p-7 text-left transition duration-300 hover:-translate-y-1.5 hover:border-brand-300 hover:shadow-theme-lg dark:hover:border-brand-500/50">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 transition duration-300 group-hover:bg-brand-500 group-hover:text-white dark:bg-brand-500/10 dark:text-brand-400 dark:group-hover:bg-brand-500 dark:group-hover:text-white">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}"/>
                                </svg>
                            </div>

                            <h3 class="font-semibold">{{ $titulo }}</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $resumo }}</p>

                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-500 opacity-0 transition duration-300 group-hover:opacity-100 dark:text-brand-400">
                                Saiba mais
                                <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5"/>
                                </svg>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Uma frase, sem lista e sem cartao. Era uma secao inteira com
                 cabecalho, paragrafo e quatro itens que repetiam em lista o que
                 o paragrafo ja dizia. O argumento e forte o bastante para ficar
                 sozinho. --}}
            <section class="border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                <div class="mx-auto w-full max-w-3xl px-6 py-14 text-center">
                    <p class="text-xl leading-relaxed text-gray-600 sm:text-2xl dark:text-gray-300">
                        Metade do país não tem limite de cartão para uma compra de três mil reais,
                        e boa parte dela tem renda para pagar em 12x. O boleto parcelado alcança
                        esse comprador <strong class="font-semibold text-gray-800 dark:text-white">sem mudar o seu preço</strong>.
                    </p>
                </div>
            </section>

            {{-- "Para quem e" e as duvidas eram duas colunas lado a lado, e
                 a das duvidas tinha o dobro da altura: a pagina terminava
                 torta, com meia coluna vazia. Cada uma ganhou a sua faixa. --}}
            <section class="mx-auto w-full max-w-[87rem] px-6 py-16">
                <div class="mb-8">
                    <span class="text-sm font-medium tracking-wide text-brand-500 uppercase dark:text-brand-400">
                        Perfis
                    </span>
                    <h2 class="mt-1 text-3xl font-semibold text-gray-800 dark:text-white">Para quem é</h2>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($perfis as [$titulo, $icone, $resumo, $detalhe])
                        <button type="button" @click="detalhe = { titulo: @js($titulo), texto: @js($detalhe), icone: @js($icone) }"
                                class="group cartao cursor-pointer p-7 text-left transition duration-300 hover:-translate-y-1.5 hover:border-brand-300 hover:shadow-theme-lg dark:hover:border-brand-500/50">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 transition duration-300 group-hover:bg-brand-500 group-hover:text-white dark:bg-brand-500/10 dark:text-brand-400 dark:group-hover:bg-brand-500 dark:group-hover:text-white">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}"/>
                                </svg>
                            </div>

                            <h3 class="font-semibold">{{ $titulo }}</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $resumo }}</p>

                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-500 opacity-0 transition duration-300 group-hover:opacity-100 dark:text-brand-400">
                                Saiba mais
                                <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5"/>
                                </svg>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="border-t border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                {{-- Mesmo container das outras secoes, e nao uma coluna
                     estreita no meio: a pagina inteira alinha em 87rem, e uma
                     faixa centrada no fim quebrava o prumo bem no ponto em que
                     a pessoa esta decidindo.

                     O titulo fica na coluna da esquerda e as perguntas na
                     direita, como o olho ja percorre o resto da pagina. --}}
                <div class="mx-auto grid w-full max-w-[87rem] gap-10 px-6 py-16 lg:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <span class="text-sm font-medium tracking-wide text-brand-500 uppercase dark:text-brand-400">
                            Dúvidas
                        </span>
                        <h2 class="mt-1 text-3xl font-semibold text-gray-800 dark:text-white">Perguntas frequentes</h2>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">
                            As quatro que todo produtor faz antes de fechar. Se a sua não está aqui,
                            fale com a equipe.
                        </p>
                    </div>

                    {{-- Uma aberta por vez. Accordion e proposital: a lista
                         inteira aberta vira parede de texto, e quem chega ate
                         aqui tem uma duvida especifica, nao quatro. --}}
                    <div class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($duvidas as $i => $item)
                            <div>
                                <button type="button" class="flex w-full items-center justify-between gap-4 py-5 text-left text-lg font-medium transition hover:text-brand-600 dark:hover:text-brand-400"
                                        @click="duvida = duvida === {{ $i }} ? null : {{ $i }}"
                                        :aria-expanded="duvida === {{ $i }}">
                                    {{ $item['pergunta'] }}
                                    <svg class="size-5 shrink-0 text-gray-400 transition" :class="duvida === {{ $i }} && 'rotate-180'"
                                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>
                                <p x-show="duvida === {{ $i }}" x-transition.opacity.duration.200ms
                                   class="pb-5 text-gray-500 dark:text-gray-400">
                                    {{ $item['resposta'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

        </main>

        {{-- O detalhe de um cartao. Um overlay so para todos: o conteudo vem
             do cartao clicado, entao acrescentar um assunto novo e acrescentar
             uma linha no array la em cima, e nao mais um bloco de markup. --}}
        <div x-cloak x-show="detalhe !== null" x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
             @click.self="detalhe = null" role="dialog" aria-modal="true">
            {{-- Mesma construcao do popup dos pilares da pagina inicial: a
                 grade viva no cabecalho, o simbolo do assunto numa caixa e o
                 texto no corpo branco. Duas caixas diferentes para a mesma
                 funcao fariam a pessoa achar que mudou de site. --}}
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="grade-viva relative bg-gray-300/35 px-7 py-6 dark:bg-black/50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                            <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="detalhe?.icone"/>
                            </svg>
                        </div>

                        <button type="button" @click="detalhe = null" aria-label="Fechar"
                                class="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                            </svg>
                        </button>
                    </div>

                    <h2 class="mt-4 text-xl font-semibold tracking-tight text-gray-800 dark:text-white" x-text="detalhe?.titulo"></h2>
                </div>

                <div class="p-7">
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300" x-text="detalhe?.texto"></p>

                    <x-avalia.botao variante="secundario" class="mt-6 w-full" @click="detalhe = null" type="button">
                        Fechar
                    </x-avalia.botao>
                </div>
            </div>
        </div>

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
            {{-- Sem marca no pe. Ela ja esta no topo, e a pagina nao e longa
                 o bastante para alguem esquecer onde esta: no rodape ela so
                 disputava espaco com o que se vem buscar aqui, que e o contato
                 e o registro da empresa. --}}
            <div class="mx-auto flex w-full max-w-[87rem] flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                <a class="hover:text-brand-500" href="mailto:{{ Empresa::email() }}">{{ Empresa::email() }}</a>

                <p class="text-center text-xs leading-relaxed text-gray-400 dark:text-gray-500">
                    © {{ now()->year }} {{ Empresa::razaoSocial() }} · CNPJ {{ Empresa::cnpj() }}<br>
                    {{ Empresa::localidade() }}
                </p>

                <a href="{{ route('inicio') }}" class="hover:text-brand-500">avaliaone.com.br</a>
            </div>
        </footer>
    </div>
@endsection
