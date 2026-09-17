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
         x-data="{ duvida: null }">

        <header class="fixed inset-x-0 top-0 z-40 border-b border-gray-200 bg-white/95 shadow-theme-md backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
            <div class="mx-auto flex h-[60px] w-full max-w-[87rem] items-center justify-between px-6">
                <a href="{{ route('inicio') }}" aria-label="Início" class="flex items-center gap-3">
                    <x-avalia.logotipo :tamanho="34" texto="1.3rem" />
                    {{-- A etiqueta e o que separa o produto da marca: a casa e
                         Avalia One, e 360 e o nome da estrutura de cobranca. --}}
                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
                </a>

                <nav class="flex items-center gap-3">
                    <a href="#pre-cadastro" class="botao botao-primario">Quero vender parcelado</a>
                </nav>
            </div>
        </header>

        <main class="pt-[60px]">
            {{-- Abertura. A promessa e a venda que hoje nao acontece, e nao a
                 tecnologia: quem vende curso ou serviço perde negocio no "não
                 tenho limite no cartão", e e esse o problema que se resolve. --}}
            <section class="mx-auto w-full max-w-[87rem] px-6 py-16 sm:py-24">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        Avalia 360 · Cobrança
                    </span>

                    <h1 class="mt-5 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                        Parcele no boleto e no Pix<br>
                        <span class="text-brand-500">sem depender do cartão.</span>
                    </h1>

                    <p class="mt-5 max-w-2xl text-lg text-gray-500 dark:text-gray-400">
                        Seu cliente fecha em até doze vezes mesmo sem limite no cartão.
                        Você acompanha cada parcela, e a régua de cobrança é nossa.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="#pre-cadastro" class="botao botao-primario">Fazer meu pré-cadastro</a>
                        <a href="{{ Suporte::whatsapp('Quero conhecer o Avalia 360') }}"
                           target="_blank" rel="noopener noreferrer" class="botao botao-secundario">
                            Falar com a equipe
                        </a>
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
                            <div class="cartao">
                                <span class="flex size-9 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">{{ $passo['n'] }}</span>
                                <h3 class="mt-4 font-semibold">{{ $passo['titulo'] }}</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $passo['texto'] }}</p>
                            </div>
                        @endforeach
                    </div>
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

            <section id="pre-cadastro" class="border-t border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                <div class="mx-auto w-full max-w-3xl px-6 py-16">
                    <h2 class="text-2xl font-semibold tracking-tight">Pré-cadastro de produtor</h2>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">
                        Conte o essencial e a equipe retorna com as condições para o seu tipo de venda.
                        Retornamos em horário comercial.
                    </p>

                    @if (session('cobranca_ok'))
                        <div class="aviso aviso-ok mt-6">
                            Pedido recebido. A equipe do Avalia 360 entra em contato pelo WhatsApp informado.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('cobranca.pre-cadastro') }}" class="mt-8 grid gap-5 sm:grid-cols-2">
                        @csrf

                        {{-- A isca. Fica fora da vista e sem rotulo alcancavel:
                             pessoa nao ve, robo preenche tudo. --}}
                        <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                        <div class="sm:col-span-2">
                            <label for="nome" class="rotulo-campo">Nome completo</label>
                            <input id="nome" name="nome" type="text" class="campo" required value="{{ old('nome') }}">
                            @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="documento" class="rotulo-campo">CPF ou CNPJ</label>
                            <input id="documento" name="documento" type="text" class="campo" required value="{{ old('documento') }}">
                            <span class="ajuda-campo">Pessoa física ou jurídica, tanto faz.</span>
                            @error('documento') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="whatsapp" class="rotulo-campo">WhatsApp</label>
                            <input id="whatsapp" name="whatsapp" type="text" class="campo" required
                                   placeholder="(34) 99999-9999" value="{{ old('whatsapp') }}">
                            @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="email" class="rotulo-campo">E-mail</label>
                            <input id="email" name="email" type="email" class="campo" required value="{{ old('email') }}">
                            @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="ticket_medio" class="rotulo-campo">Ticket médio</label>
                            <input id="ticket_medio" name="ticket_medio" type="text" class="campo" required
                                   placeholder="2.500,00" value="{{ old('ticket_medio') }}">
                            <span class="ajuda-campo">Quanto custa, em média, o que você vende.</span>
                            @error('ticket_medio') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="volume_mensal" class="rotulo-campo">Volume mensal estimado</label>
                            <select id="volume_mensal" name="volume_mensal" class="campo" required>
                                <option value="">Escolha uma faixa</option>
                                @foreach ($volumes as $faixa)
                                    <option value="{{ $faixa }}" @selected(old('volume_mensal') === $faixa)>{{ $faixa }}</option>
                                @endforeach
                            </select>
                            @error('volume_mensal') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <button type="submit" class="botao botao-primario">Enviar pré-cadastro</button>
                            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                                Seus dados ficam só com a Avalia One e servem apenas para este contato.
                            </p>
                        </div>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-gray-100 dark:border-gray-800">
            <div class="mx-auto flex w-full max-w-[87rem] flex-wrap items-center justify-between gap-4 px-6 pt-3 pb-0.5 text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('inicio') }}" aria-label="Início">
                    <x-avalia.logotipo :tamanho="24" />
                </a>
                <a class="hover:text-brand-500" href="mailto:{{ Empresa::email() }}">{{ Empresa::email() }}</a>
            </div>

            {{-- A identificacao inteira numa linha so, igual a da pagina
                 inicial: as duas paginas publicas fecham do mesmo jeito. --}}
            <p class="mx-auto w-full max-w-[87rem] px-6 pb-3 text-center text-xs text-gray-400 dark:text-gray-500">
                © {{ now()->year }} {{ Empresa::razaoSocial() }} · CNPJ {{ Empresa::cnpj() }} · {{ Empresa::endereco() }}
            </p>
        </footer>
    </div>
@endsection
