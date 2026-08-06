@extends('layouts.fullscreen-layout', ['title' => 'Consultas de crédito para empresas'])

@php
    use App\Support\Suporte;

    // Celulas que acendem na grade do topo. Posicoes multiplas de 42px, o passo
    // da grade, para a celula cair exatamente dentro de um quadradinho.
    $celulas = [
        ['top' => 1, 'left' => 2, 'atraso' => '0s'],
        ['top' => 3, 'left' => 5, 'atraso' => '0.9s'],
        ['top' => 6, 'left' => 1, 'atraso' => '1.7s'],
        ['top' => 2, 'left' => 9, 'atraso' => '2.4s'],
        ['top' => 8, 'left' => 7, 'atraso' => '3.2s'],
        ['top' => 5, 'left' => 12, 'atraso' => '3.9s'],
        ['top' => 9, 'left' => 3, 'atraso' => '4.6s'],
        ['top' => 4, 'left' => 15, 'atraso' => '1.3s'],
    ];

    // Os tres pilares e o texto completo que o popup de cada um mostra. Vivem
    // juntos para o card e o detalhe nunca divergirem.
    $pilares = [
        'dados' => [
            'titulo' => 'Decida com dados',
            'resumo' => 'Consulte crédito e cadastro antes de fechar a venda a prazo. O resultado chega em segundos, direto no painel.',
            'detalhe' => 'Antes de parcelar, sua equipe consulta o CPF ou o CNPJ e recebe a leitura de risco na hora. Sem instalação: é abrir o painel e consultar.',
            'itens' => [
                'Consulta de CPF e CNPJ com pontuação',
                'Restrições e histórico de mercado',
                'Finalidade e responsável registrados',
                'Resultado em segundos, direto no painel',
            ],
            'icone' => 'M3 17l5-5 4 4 8-9M16 7h5v5',
        ],
        'fraude' => [
            'titulo' => 'Evite a fraude',
            'resumo' => 'Confirme quem está do outro lado do negócio, do CPF ao veículo, antes de o prejuízo entrar pela porta.',
            'detalhe' => 'O golpe chega bem vestido. O custo de conferir é o de uma consulta; o de não conferir é o prejuízo inteiro.',
            'itens' => [
                'Situação cadastral de CPF e CNPJ',
                'Confirmação dos dados da empresa',
                'Procedência veicular completa',
                'Resposta antes de o negócio fechar',
            ],
            'icone' => 'M12 3l7 3v5c0 4.5-3 8.6-7 10-4-1.4-7-5.5-7-10V6l7-3zM9.5 12l1.8 1.8 3.2-3.6',
        ],
        'preco' => [
            'titulo' => 'Pague pelo que usar',
            'resumo' => 'Cada consulta tem preço definido em contrato, e o mês fecha em uma fatura única. Consumo e franquia à vista no painel, sem surpresa.',
            'detalhe' => 'Nada de pacote que expira nem cobrança que aparece do nada. Quem usa pouco paga o mínimo do plano; quem usa muito sabe exatamente quanto.',
            'itens' => [
                'Preço por consulta definido em contrato',
                'Consumo e franquia à vista no painel',
                'Fatura única no fim do mês',
                'Composição aberta, consulta por consulta',
            ],
            'icone' => 'M9 7h9a2 2 0 012 2v9a2 2 0 01-2 2H9a2 2 0 01-2-2V9a2 2 0 012-2zM7 15H6a2 2 0 01-2-2V5a2 2 0 012-2h8a2 2 0 012 2v1M11 12h5M11 15.5h5',
        ],
    ];
@endphp

@section('content')
    <div class="min-h-screen bg-white text-gray-800 dark:bg-gray-900 dark:text-white/90"
         x-data="{ aberto: null }"
         x-init="@if (session('interesse_ok')) aberto = 'obrigado' @elseif ($errors->any()) aberto = 'campanha' @endif"
         @keydown.escape.window="aberto = null">

        {{-- Topo fixo. Sombra e fundo quase solido para separar do conteudo:
             com a grade animada passando por baixo, o header translucido
             sumia na pagina. --}}
        <header class="fixed inset-x-0 top-0 z-40 border-b border-gray-200 bg-white/95 shadow-theme-md backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
            <div class="mx-auto flex h-20 w-full max-w-7xl items-center justify-between px-6">
                <x-avalia.logotipo :tamanho="40" />

                <nav class="flex items-center gap-2 sm:gap-3">
                    {{-- Claro e escuro, o mesmo interruptor do painel. --}}
                    <button @click="$store.theme.toggle()" aria-label="Alternar tema"
                            class="flex size-11 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="hidden size-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="4"/>
                            <path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/>
                        </svg>
                        <svg class="size-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                        </svg>
                    </button>

                    <x-avalia.botao variante="secundario" :href="route('entrar')">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a1 1 0 011 1v16a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3"/>
                        </svg>
                        Entrar
                    </x-avalia.botao>

                    <a href="{{ Suporte::whatsapp('Quero conhecer a Avalia') }}" target="_blank" rel="noopener noreferrer"
                       class="botao botao-primario hidden sm:inline-flex">
                        <svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23Z"/>
                        </svg>
                        Falar com um consultor
                    </a>
                </nav>
            </div>
        </header>

        {{-- Herói: a grade viva da marca ao fundo, a promessa na frente. --}}
        <section class="grade-viva relative overflow-hidden pt-20">
            <div aria-hidden="true" class="absolute inset-0">
                @foreach ($celulas as $celula)
                    <span class="celula-viva"
                          style="top: {{ $celula['top'] * 42 }}px; left: {{ $celula['left'] * 42 }}px; animation-delay: {{ $celula['atraso'] }}"></span>
                @endforeach
            </div>
            <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent to-white dark:to-gray-900"></div>

            <div class="relative mx-auto grid w-full max-w-7xl items-center gap-10 px-6 py-8 lg:grid-cols-2 lg:py-12">
                <div>
                    <span class="entra-suave inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
                        <span class="size-1.5 rounded-full bg-brand-500"></span>
                        Consultas de crédito para empresas
                    </span>

                    <h1 class="entra-suave mt-5 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl"
                        style="animation-delay: 0.1s">
                        Venda a prazo com a<br>
                        <span class="text-brand-500">decisão certa.</span>
                    </h1>

                    <p class="entra-suave mt-5 max-w-lg text-lg text-gray-500 dark:text-gray-400"
                       style="animation-delay: 0.2s">
                        Crédito, cadastro e veicular em um painel só. Sua empresa consulta
                        antes de vender, acompanha o consumo e recebe uma fatura única por mês.
                    </p>

                    <div class="entra-suave mt-8 flex flex-wrap items-center gap-3" style="animation-delay: 0.3s">
                        <a href="{{ Suporte::whatsapp('Quero contratar a Avalia') }}" target="_blank" rel="noopener noreferrer"
                           class="botao botao-primario">
                            <svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Z"/>
                            </svg>
                            Quero contratar
                        </a>
                        <x-avalia.botao variante="secundario" :href="route('entrar')">
                            Já sou cliente
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5"/>
                            </svg>
                        </x-avalia.botao>
                    </div>

                    <ul class="entra-suave mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500 dark:text-gray-400"
                        style="animation-delay: 0.4s">
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            Preço por consulta
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            Atendimento humano
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            LGPD desde o desenho
                        </li>
                    </ul>
                </div>

                {{-- Uma sessao de consultas acontecendo: o medidor refaz a
                     leitura a cada cliente do carrossel, a pontuacao sai na cor
                     de bureau e a barra cresce junto.

                     Fundo solido de proposito, e nao o vidro do cartao padrao:
                     com a grade animada passando por baixo, transparencia aqui
                     virava ruido. E uma janela do produto, nao um vitral.

                     Todo nome e ficticio e todo documento e mascarado, e o
                     cartao diz "Simulação": vitrine publica exibindo consulta
                     que parecesse real seria exatamente o vazamento que o
                     produto promete impedir. --}}
                {{-- O padding de cima e de baixo reserva a faixa onde os chips
                     flutuam: fora da janela, nunca sobre o conteudo dela.

                     O carrossel e do Alpine, nao de relogio CSS: setinha
                     clicada troca a consulta na hora e reinicia o compasso.
                     Cada slide reaparece via display, o que reinicia as
                     animacoes de contagem e barra sozinhas; o medidor e
                     reiniciado a mao, porque vive fora dos slides.

                     Vidro de volta: com os chips fora da janela, a
                     translucidez sobre a grade viva e contraste, nao ruido. --}}
                <div class="entra-suave relative mx-auto w-full max-w-[30rem] pt-12" style="animation-delay: 0.25s"
                     x-data="{
                         atual: 0,
                         compasso: null,
                         iniciar() {
                             if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                 this.recomeca();
                             }
                         },
                         recomeca() {
                             clearInterval(this.compasso);
                             this.compasso = setInterval(() => this.avanca(1, false), 4200);
                         },
                         avanca(passo, manual = true) {
                             this.atual = (this.atual + passo + 4) % 4;
                             this.leDeNovo();
                             if (manual) this.recomeca();
                         },
                         vaiPara(indice) {
                             this.atual = indice;
                             this.leDeNovo();
                             this.recomeca();
                         },
                         leDeNovo() {
                             this.$refs.medidor.querySelectorAll('.medidor-ponteiro-vivo, .medidor-faixa-viva').forEach((el) => {
                                 el.style.animation = 'none';
                                 void el.offsetWidth;
                                 el.style.animation = '';
                             });
                         },
                     }" x-init="iniciar()">
                    <div class="relative z-10 rounded-2xl border border-gray-200 bg-white/70 backdrop-blur-md shadow-theme-lg dark:border-gray-700 dark:bg-gray-800/70">
                        <div class="border-b border-gray-100 px-6 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Painel de consultas</span>
                        </div>

                        <div class="flex flex-col items-center px-6 pt-6" x-ref="medidor">
                            <x-avalia.medidor :tamanho="140" vivo />
                        </div>

                        {{-- Setinhas nas duas cores do numero: rosa volta,
                             azul avanca. --}}
                        <div class="relative mx-6 mt-4 h-[92px]">
                            <button type="button" @click="avanca(-1)" aria-label="Consulta anterior"
                                    class="absolute top-1/2 -left-3 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-theme-pink-500/30 bg-white/80 text-theme-pink-500 shadow-theme-xs transition hover:bg-theme-pink-500 hover:text-white dark:bg-gray-800/80">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <button type="button" @click="avanca(1)" aria-label="Próxima consulta"
                                    class="absolute top-1/2 -right-3 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-brand-500/30 bg-white/80 text-brand-500 shadow-theme-xs transition hover:bg-brand-500 hover:text-white dark:bg-gray-800/80">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            @foreach ([
                                ['tipo' => 'CNPJ', 'nome' => 'Casa Sul Materiais', 'doc' => '12.345.678/0001-**', 'pontos' => 782, 'faixa' => 78],
                                ['tipo' => 'CPF', 'nome' => 'Marcelo Silveira', 'doc' => '***.482.916-**', 'pontos' => 645, 'faixa' => 64],
                                ['tipo' => 'CNPJ', 'nome' => 'Reparo Tecnologia', 'doc' => '98.765.432/0001-**', 'pontos' => 823, 'faixa' => 82],
                                ['tipo' => 'CPF', 'nome' => 'Helena Duarte', 'doc' => '***.157.204-**', 'pontos' => 597, 'faixa' => 60],
                            ] as $i => $consulta)
                                <div x-cloak x-show="atual === {{ $i }}"
                                     class="consulta-entra absolute inset-0 flex items-center justify-between gap-4 px-7">
                                    <div class="min-w-0">
                                        <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $consulta['tipo'] === 'CPF' ? 'bg-theme-pink-500/10 text-theme-pink-500' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' }}">
                                            {{ $consulta['tipo'] }}
                                        </span>
                                        <p class="mt-1.5 truncate font-medium text-gray-800 dark:text-white/90">{{ $consulta['nome'] }}</p>
                                        <p class="text-xs text-gray-400 tabular-nums dark:text-gray-500">{{ $consulta['doc'] }}</p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <span class="numero-conta text-4xl font-semibold tabular-nums"
                                              style="--alvo: {{ $consulta['pontos'] }}"></span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">pontos</span>
                                        <div class="mt-1.5 ml-auto h-1 w-20 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                            <div class="barra-bureau barra-cresce h-full rounded-full"
                                                 style="width: {{ $consulta['faixa'] }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-2 mb-4 flex items-center justify-center gap-1.5">
                            @foreach (range(0, 3) as $ponto)
                                <button type="button" @click="vaiPara({{ $ponto }})"
                                        aria-label="Ver consulta {{ $ponto + 1 }}"
                                        class="size-1.5 rounded-full transition"
                                        :class="atual === {{ $ponto }} ? 'scale-125 bg-brand-500' : 'bg-brand-500/20 hover:bg-brand-500/40'"></button>
                            @endforeach
                        </div>

                        {{-- Rodape da janela: a acao que o cliente faria em
                             seguida. Parte da simulacao, como o resto. --}}
                        <div class="flex items-center justify-between border-t border-gray-100 px-6 py-3.5 dark:border-gray-700" aria-hidden="true">
                            <span class="inline-flex cursor-default items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 dark:border-gray-600 dark:text-gray-300">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                                </svg>
                                Exportar relatório
                                <span class="rounded bg-error-50 px-1 text-[10px] font-bold text-error-600 dark:bg-error-500/15 dark:text-error-400">PDF</span>
                            </span>
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">Fatura única no fim do mês</span>
                        </div>
                    </div>

                    <span class="absolute top-4 right-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-400 dark:bg-white/10 dark:text-gray-400">
                        Simulação
                    </span>

                    <div class="flutua absolute top-9 left-0 z-20 flex items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 shadow-theme-md sm:-left-4 dark:border-gray-700 dark:bg-gray-800">
                        <span class="etiqueta etiqueta-sucesso">Concluída</span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Consulta de CNPJ</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- O que a empresa leva. O card e o convite; o popup, a conversa
             inteira. Preco de tabela continua atras do login. --}}
        <section class="mx-auto w-full max-w-7xl px-6 py-10 lg:py-14">
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($pilares as $chave => $pilar)
                    <button type="button" @click="aberto = '{{ $chave }}'"
                            class="group cartao cursor-pointer p-7 text-left transition duration-300 hover:-translate-y-1.5 hover:border-brand-300 hover:shadow-theme-lg dark:hover:border-brand-500/50">
                        <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 transition duration-300 group-hover:bg-brand-500 group-hover:text-white dark:bg-brand-500/10 dark:text-brand-400 dark:group-hover:bg-brand-500 dark:group-hover:text-white">
                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $pilar['icone'] }}"/>
                            </svg>
                        </div>
                        <h2 class="font-semibold">{{ $pilar['titulo'] }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $pilar['resumo'] }}</p>

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

        {{-- Campanha de adesao: o convite direto, no fundo escuro da marca. --}}
        <section class="mx-auto w-full max-w-7xl px-6 pb-16 lg:pb-24">
            <div class="grade-viva-escura relative overflow-hidden rounded-3xl bg-brand-950 px-6 py-14 text-center sm:px-14">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white">
                    <span class="size-1.5 animate-pulse rounded-full bg-success-500"></span>
                    Campanha de adesão aberta
                </span>

                <h2 class="mx-auto mt-5 max-w-2xl text-3xl font-semibold text-white sm:text-4xl">
                    Comece a consultar ainda esta semana
                </h2>

                <p class="mx-auto mt-4 max-w-xl text-gray-400">
                    Novos clientes contam com condições especiais de adesão e uma
                    proposta sob medida para o volume da sua empresa. Deixe seu contato
                    e um consultor retorna ainda hoje.
                </p>

                <button type="button" @click="aberto = 'campanha'"
                        class="mt-8 inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-medium text-brand-950 transition hover:bg-brand-50">
                    Quero aproveitar a campanha
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M18 12H6"/>
                    </svg>
                </button>
            </div>
        </section>

        <footer class="border-t border-gray-100 dark:border-gray-800">
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-gray-500 dark:text-gray-400">
                <x-avalia.logotipo :tamanho="24" />
                <p>© {{ now()->year }} Avalia. Consultas com finalidade declarada e trilha de auditoria.</p>
                <div class="flex items-center gap-5">
                    <a class="hover:text-brand-500" href="{{ route('entrar') }}">Entrar</a>
                    <a class="hover:text-brand-500" href="{{ Suporte::whatsapp() }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                </div>
            </div>
        </footer>

        {{-- Popups. Um so trilho para todos: fundo escurecido, cartao ao
             centro, Esc ou clique fora fecham. --}}
        @foreach ($pilares as $chave => $pilar)
            <div x-cloak x-show="aberto === '{{ $chave }}'" x-transition.opacity.duration.200ms
                 class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
                 @click.self="aberto = null" role="dialog" aria-modal="true" aria-label="{{ $pilar['titulo'] }}">
                <div class="entra-popup w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">

                    {{-- Faixa da marca: o mesmo fundo da campanha, com a grade
                         e a logo. E peca de venda, e assina como tal. --}}
                    <div class="grade-viva-escura relative bg-brand-950 px-7 py-6">
                        <div class="flex items-start justify-between">
                            <div class="flex size-14 items-center justify-center rounded-2xl bg-white/10 text-white">
                                <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $pilar['icone'] }}"/>
                                </svg>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-avalia.logotipo :tamanho="26" claro />
                                <button type="button" @click="aberto = null" aria-label="Fechar"
                                        class="rounded-lg p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <h3 class="mt-5 text-2xl font-semibold text-white">{{ $pilar['titulo'] }}</h3>
                        <p class="mt-1.5 text-sm text-gray-400">{{ $pilar['detalhe'] }}</p>
                    </div>

                    <div class="p-7">
                        <ul class="grid gap-3 sm:grid-cols-2">
                            @foreach ($pilar['itens'] as $item)
                                <li class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
                                        </svg>
                                    </span>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ Suporte::whatsapp($pilar['titulo']) }}" target="_blank" rel="noopener noreferrer"
                           class="botao botao-primario mt-7 w-full">
                            <svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Z"/>
                            </svg>
                            Falar com um consultor
                        </a>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- O formulario da campanha. Os dados entram no nosso banco e a
             conversa comeca do nosso lado: nome e telefone sao dado pessoal e
             nao viajam em URL de WhatsApp. --}}
        <div x-cloak x-show="aberto === 'campanha'" x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
             @click.self="aberto = null" role="dialog" aria-modal="true" aria-label="Pedido de contato">
            <div class="entra-popup max-h-[92vh] w-full max-w-md overflow-y-auto rounded-2xl border border-gray-200 bg-white p-7 shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold">Quase lá</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Deixe seu contato e um consultor retorna ainda hoje.
                        </p>
                    </div>
                    <button type="button" @click="aberto = null" aria-label="Fechar"
                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('interesse.salvar') }}" class="mt-5 space-y-4">
                    @csrf

                    {{-- Campo que nenhuma pessoa ve. Robo preenche tudo, e o
                         servidor descarta em silencio o que vier com ele. --}}
                    <input type="text" name="site" value="" tabindex="-1" autocomplete="off"
                           class="hidden" aria-hidden="true">

                    <div>
                        <label for="int-nome" class="rotulo-campo">Seu nome</label>
                        <input id="int-nome" name="nome" type="text" class="campo" required
                               maxlength="120" value="{{ old('nome') }}" placeholder="Como podemos te chamar">
                        @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="int-empresa" class="rotulo-campo">Nome da empresa</label>
                        <input id="int-empresa" name="empresa" type="text" class="campo" required
                               maxlength="150" value="{{ old('empresa') }}">
                        @error('empresa') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="int-telefone" class="rotulo-campo">Telefone com DDD</label>
                            <input id="int-telefone" name="telefone" type="tel" class="campo" required
                                   inputmode="tel" maxlength="20" value="{{ old('telefone') }}" placeholder="(11) 99999-0000">
                            @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="int-funcionarios" class="rotulo-campo">Funcionários</label>
                            <select id="int-funcionarios" name="funcionarios" class="campo" required>
                                <option value="" disabled @selected(! old('funcionarios'))>Escolha</option>
                                @foreach (['Até 5', '6 a 20', '21 a 50', 'Mais de 50'] as $faixa)
                                    <option value="{{ $faixa }}" @selected(old('funcionarios') === $faixa)>{{ $faixa }}</option>
                                @endforeach
                            </select>
                            @error('funcionarios') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="int-email" class="rotulo-campo">E-mail</label>
                        <input id="int-email" name="email" type="email" class="campo" required
                               maxlength="150" value="{{ old('email') }}" placeholder="voce@empresa.com.br">
                        @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <x-avalia.botao class="w-full">
                        Enviar pedido de contato
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M18 12H6"/>
                        </svg>
                    </x-avalia.botao>

                    <p class="text-center text-xs text-gray-400 dark:text-gray-500">
                        Retornamos em horário comercial. Seus dados ficam só com a Avalia.
                    </p>
                </form>
            </div>
        </div>

        {{-- Confirmacao de que o pedido chegou. --}}
        <div x-cloak x-show="aberto === 'obrigado'" x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
             @click.self="aberto = null" role="dialog" aria-modal="true" aria-label="Pedido recebido">
            <div class="entra-popup w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-semibold">Pedido recebido</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Obrigado pelo interesse. Um consultor entra em contato ainda hoje,
                    em horário comercial.
                </p>
                <x-avalia.botao variante="secundario" class="mt-6 w-full" @click="aberto = null" type="button">
                    Fechar
                </x-avalia.botao>
            </div>
        </div>
    </div>
@endsection
