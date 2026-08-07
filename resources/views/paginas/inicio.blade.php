@extends('layouts.fullscreen-layout', ['title' => 'Pesquisa de score para empresas'])

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
            'resumo' => 'Pesquise o score e os dados públicos antes de fechar a venda a prazo. O resultado chega em segundos, direto no painel.',
            'detalhe' => 'Antes de parcelar, sua equipe pesquisa o CPF ou o CNPJ e recebe a pontuação e o histórico na hora. A Avalia entrega a informação; a decisão de vender é sempre da sua empresa.',
            'itens' => [
                'Pontuação de score na versão mais recente do modelo',
                'Tendência e estabilidade do comportamento ao longo do tempo',
                'Restrições e histórico público de mercado',
                'Finalidade e responsável registrados em cada pesquisa',
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
         @scroll.window.passive="rolou = window.scrollY > 80"
         x-data="{
             aberto: null,
             rolou: false,
             pilares: ['dados', 'fraude', 'preco'],
             arrasto: 0,
             inicioX: null,
             idxPilar() { return Math.max(0, this.pilares.indexOf(this.aberto)) },
             anteriorPilar() { if (this.idxPilar() > 0) this.aberto = this.pilares[this.idxPilar() - 1] },
             proximoPilar() { if (this.idxPilar() < 2) this.aberto = this.pilares[this.idxPilar() + 1] },
             comecaArrasto(e) { this.inicioX = e.clientX },
             moveArrasto(e) { if (this.inicioX !== null) this.arrasto = e.clientX - this.inicioX },
             soltaArrasto() {
                 if (this.inicioX === null) return;
                 const d = this.arrasto;
                 this.arrasto = 0;
                 this.inicioX = null;
                 if (d < -60) this.proximoPilar();
                 else if (d > 60) this.anteriorPilar();
             },
         }"
         x-init="@if (session('interesse_ok')) aberto = 'obrigado' @elseif ($errors->any() || request()->boolean('interesse')) aberto = 'campanha' @endif"
         @keydown.escape.window="aberto = null">

        {{-- Topo fixo. Sombra e fundo quase solido para separar do conteudo:
             com a grade animada passando por baixo, o header translucido
             sumia na pagina. --}}
        <header class="fixed inset-x-0 top-0 z-40 border-b border-gray-200 bg-white/95 shadow-theme-md backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 dark:shadow-[0_6px_18px_rgb(0_0_0/0.45)]">
            <div class="mx-auto flex h-[72px] w-full max-w-[87rem] items-center justify-between px-6">
                <a href="{{ route('inicio') }}" aria-label="Início">
                    <x-avalia.logotipo :tamanho="40" one />
                </a>

                <nav class="flex items-center gap-2 pr-12 sm:gap-3 sm:pr-14 min-[1550px]:pr-0">
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

            {{-- Claro e escuro na ponta extrema, de proposito fora do
                 alinhamento das colunas: e ferramenta da pagina, nao passo do
                 funil, e a posicao diz isso. --}}
            <button @click="$store.theme.toggle()" aria-label="Alternar tema"
                    class="absolute top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 sm:right-4 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                <svg class="hidden size-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="4"/>
                    <path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/>
                </svg>
                <svg class="size-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                </svg>
            </button>
        </header>

        {{-- Herói: a grade viva da marca ao fundo, a promessa na frente.

             A seção mede a si mesma e distribui azulejos inteiros: o passo da
             grade vira variável, calculada para a largura e a altura fecharem
             sem azulejo cortado na borda. As células acesas usam o mesmo
             passo, então continuam caindo exatamente dentro dos quadradinhos. --}}
        <section class="grade-viva relative overflow-hidden pt-[72px]"
                 x-data="{
                     ajustar() {
                         const r = this.$el.getBoundingClientRect();
                         const cx = Math.max(1, Math.round(r.width / 42));
                         const cy = Math.max(1, Math.round(r.height / 42));
                         this.$el.style.setProperty('--passo-x', (r.width / cx) + 'px');
                         this.$el.style.setProperty('--passo-y', (r.height / cy) + 'px');
                     },
                     px: 42, py: 42, rastro: [], seq: 0, ultimo: null,
                     rastreia(e) {
                         const rct = this.$el.getBoundingClientRect();
                         const est = getComputedStyle(this.$el);
                         this.px = parseFloat(est.getPropertyValue('--passo-x')) || 42;
                         this.py = parseFloat(est.getPropertyValue('--passo-y')) || 42;
                         const c = Math.floor((e.clientX - rct.left) / this.px);
                         const r = Math.floor((e.clientY - rct.top) / this.py);
                         const chave = c + ':' + r;
                         if (chave === this.ultimo) return;
                         this.ultimo = chave;
                         const vizinhas = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, -1], [1, -1], [-1, 1]]
                             .sort(() => Math.random() - 0.5).slice(0, 3);
                         const grupo = [[0, 0], ...vizinhas].map(([dc, dr], i) => ({
                             id: ++this.seq, c: c + dc, r: r + dr, atraso: i * 90,
                         }));
                         const ids = grupo.map((g) => g.id);
                         this.rastro = [...this.rastro.slice(-4), ...grupo];
                         setTimeout(() => { this.rastro = this.rastro.filter((x) => ! ids.includes(x.id)); }, 1400);
                     },
                 }"
                 x-init="ajustar(); new ResizeObserver(() => ajustar()).observe($el)"
                 @mousemove="rastreia($event)">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                <template x-for="ponto in rastro" :key="ponto.id">
                        <span class="pointer-events-none absolute top-0 left-0"
                              :style="`width:${px - 1}px;height:${py - 1}px;transform:translate(${ponto.c * px + 1}px,${ponto.r * py + 1}px)`">
                            <span class="celula-vira block h-full w-full rounded-[2px]"
                                  :style="`animation-delay:${ponto.atraso}ms`"></span>
                        </span>
                    </template>
                    @foreach ($celulas as $celula)
                    <span class="celula-viva"
                          style="top: calc(var(--passo-y, 42px) * {{ $celula['top'] }} + 1px); left: calc(var(--passo-x, 42px) * {{ $celula['left'] }} + 1px); animation-delay: {{ $celula['atraso'] }}"></span>
                @endforeach
            </div>
            <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent to-white dark:to-gray-900"></div>

            <div class="relative mx-auto grid w-full max-w-[87rem] items-center gap-10 px-6 py-8 lg:grid-cols-2 lg:py-12">
                <div>
                    <span class="entra-suave inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
                        <span class="size-1.5 rounded-full bg-brand-500"></span>
                        Pesquisa de score para empresas
                    </span>

                    <h1 class="entra-suave mt-5 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl"
                        style="animation-delay: 0.1s">
                        Venda a prazo com a<br>
                        <span class="text-brand-500">decisão informada.</span>
                    </h1>

                    {{-- A promessa vende decisao, nao acesso a dado, e amarra a
                         consulta a uma venda do proprio cliente: e o vocabulario
                         que a Lei 12.414 e o art. 7, X, da LGPD sustentam. --}}
                    <p class="entra-suave mt-5 max-w-lg text-lg text-gray-500 dark:text-gray-400"
                       style="animation-delay: 0.2s">
                        Score, restrições e procedência veicular em um painel só. Em
                        segundos, sua empresa sabe quem está do outro lado antes de
                        vender a prazo. Informação nossa, decisão sua.
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
                <div class="entra-suave relative mx-auto w-full max-w-[34rem] pt-12 lg:mr-0 lg:ml-auto" style="animation-delay: 0.25s"
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
                             this.compasso = setInterval(() => this.avanca(1, false), 4600);
                         },
                         avanca(passo, manual = true) {
                             this.atual = (this.atual + passo + 4) % 4;
                             this.reanima();
                             if (manual) this.recomeca();
                         },
                         vaiPara(indice) {
                             this.atual = indice;
                             this.reanima();
                             this.recomeca();
                         },
                         reanima() {
                             const slide = this.$refs.trilho?.children[this.atual];
                             if (! slide) return;
                             slide.querySelectorAll('.numero-conta, .barra-cresce, .medidor-nivel-faixa, .medidor-nivel-ponteiro').forEach((el) => {
                                 el.style.animation = 'none';
                                 void el.offsetWidth;
                                 el.style.animation = '';
                             });
                         },
                     }" x-init="iniciar()">
                    <div class="relative z-10 rounded-2xl border border-gray-200 bg-white/70 backdrop-blur-md shadow-theme-lg dark:border-gray-700 dark:bg-gray-800/70">
                        <div class="border-b border-gray-100 px-7 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Painel de consultas</span>
                        </div>

                        {{-- Setinhas nas duas cores do numero, rosa volta e
                             azul avanca: centralizadas na altura da janela e
                             para fora dela, flanqueando o vidro. --}}
                        <button type="button" @click="avanca(-1)" aria-label="Consulta anterior"
                                class="absolute top-1/2 -left-4 z-20 -translate-y-1/2 transition hover:scale-125 sm:-left-11">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
                                <defs><linearGradient id="seta-esq" x1="0" y1="0" x2="24" y2="0" gradientUnits="userSpaceOnUse">
                                    <stop offset="0" stop-color="var(--color-brand-500)"/><stop offset="1" stop-color="var(--color-theme-pink-500)"/>
                                </linearGradient></defs>
                                <path stroke="url(#seta-esq)" stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button type="button" @click="avanca(1)" aria-label="Próxima consulta"
                                class="absolute top-1/2 -right-4 z-20 -translate-y-1/2 transition hover:scale-125 sm:-right-11">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
                                <defs><linearGradient id="seta-dir" x1="0" y1="0" x2="24" y2="0" gradientUnits="userSpaceOnUse">
                                    <stop offset="0" stop-color="var(--color-theme-pink-500)"/><stop offset="1" stop-color="var(--color-brand-500)"/>
                                </linearGradient></defs>
                                <path stroke="url(#seta-dir)" stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <div class="relative mx-7 mt-4 mb-2 h-[150px] overflow-hidden">
                            <div x-ref="trilho" class="flex h-full transition-transform duration-[800ms] ease-out"
                                 :style="`transform: translateX(${-atual * 100}%)`">
                            @foreach ([
                                ['tipo' => 'CNPJ', 'nome' => 'Casa Sul Materiais', 'doc' => '12.345.678/0001-**', 'pontos' => 782, 'faixa' => 78],
                                ['tipo' => 'CPF', 'nome' => 'Marcelo Silveira', 'doc' => '***.482.916-**', 'pontos' => 645, 'faixa' => 64],
                                ['tipo' => 'CNPJ', 'nome' => 'Reparo Tecnologia', 'doc' => '98.765.432/0001-**', 'pontos' => 823, 'faixa' => 82],
                                ['tipo' => 'CPF', 'nome' => 'Helena Duarte', 'doc' => '***.157.204-**', 'pontos' => 597, 'faixa' => 60],
                            ] as $i => $consulta)
                                <div class="relative h-full w-full shrink-0 px-1"
                                     style="--nivel: {{ $consulta['pontos'] / 1000 }}">
                                    {{-- O instrumento no centro do palco... --}}
                                    <div class="absolute inset-x-0 top-0 flex justify-center">
                                        <x-avalia.medidor :tamanho="132" por-nivel />
                                    </div>

                                    {{-- ...e os dados assentados na base. --}}
                                    <div class="absolute bottom-0 left-0 max-w-[46%] min-w-0">
                                        <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $consulta['tipo'] === 'CPF' ? 'bg-theme-pink-500/10 text-theme-pink-500' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' }}">
                                            {{ $consulta['tipo'] }}
                                        </span>
                                        <p class="mt-1.5 truncate font-medium text-gray-800 dark:text-white/90">{{ $consulta['nome'] }}</p>
                                        <p class="text-xs text-gray-400 tabular-nums dark:text-gray-500">{{ $consulta['doc'] }}</p>
                                    </div>

                                    <div class="absolute right-0 bottom-0 text-right">
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
                        </div>

                        <div class="mt-2 mb-3 flex items-center justify-center gap-1.5">
                            @foreach (range(0, 3) as $ponto)
                                <button type="button" @click="vaiPara({{ $ponto }})"
                                        aria-label="Ver consulta {{ $ponto + 1 }}"
                                        class="size-1.5 rounded-full transition"
                                        :class="atual === {{ $ponto }} ? 'scale-125 bg-brand-500' : 'bg-brand-500/20 hover:bg-brand-500/40'"></button>
                            @endforeach
                        </div>

                        {{-- Rodape da janela: a acao que o cliente faria em
                             seguida. Parte da simulacao, como o resto. --}}
                        <div class="flex items-center justify-between border-t border-gray-100 px-7 py-3.5 dark:border-gray-700" aria-hidden="true">
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

                    <span class="absolute top-4 left-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-400 dark:bg-white/10 dark:text-gray-400">
                        Simulação
                    </span>

                    <div class="flutua-alto absolute top-9 right-0 z-20 flex items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 shadow-theme-md sm:-right-4 dark:border-gray-700 dark:bg-gray-800">
                        <span class="etiqueta etiqueta-sucesso">Concluída</span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Consulta de CNPJ</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- O que a empresa leva. O card e o convite; o popup, a conversa
             inteira. Preco de tabela continua atras do login. --}}
        <section class="mx-auto w-full max-w-[87rem] px-6 py-10 lg:py-14">
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

        {{-- A seta de rolagem mora no primeiro ecra, fixa no rodape da
             janela: indicador que so aparece depois de rolar nao indica nada.
             Some na primeira rolagem, porque ja cumpriu o papel. O balanco
             fica num span interno para a animacao nao brigar com o
             translate-x do centro. --}}
        <a href="#campanha" aria-label="Rolar até a campanha" x-cloak x-show="! rolou"
           x-transition.opacity.duration.500ms
           class="fixed bottom-5 left-1/2 z-40 -translate-x-1/2">
            <span class="balanca block transition hover:scale-125">
                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
                    <defs><linearGradient id="seta-baixo" x1="0" y1="0" x2="24" y2="0" gradientUnits="userSpaceOnUse">
                        <stop offset="0" stop-color="var(--color-theme-pink-500)"/><stop offset="1" stop-color="var(--color-brand-500)"/>
                    </linearGradient></defs>
                    <path stroke="url(#seta-baixo)" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </a>

        {{-- Campanha de adesao: o convite direto, no fundo escuro da marca. --}}
        <section id="campanha" class="mx-auto w-full max-w-[87rem] scroll-mt-24 px-6 pb-16 lg:pb-24">
            {{-- O banner mede a si mesmo como o heroi: azulejos inteiros, e as
                 celulas acesas no canto inferior direito caem exatamente na
                 grade. --}}
            <div class="grade-viva relative overflow-hidden rounded-3xl border border-gray-200 bg-gray-300/15 px-6 py-14 text-center dark:border-gray-800 dark:bg-black/35 sm:px-14"
                 x-data="{
                     ajustar() {
                         const r = this.$el.getBoundingClientRect();
                         const cx = Math.max(1, Math.round(r.width / 42));
                         const cy = Math.max(1, Math.round(r.height / 42));
                         this.$el.style.setProperty('--passo-x', (r.width / cx) + 'px');
                         this.$el.style.setProperty('--passo-y', (r.height / cy) + 'px');
                     },
                     px: 42, py: 42, rastro: [], seq: 0, ultimo: null,
                     rastreia(e) {
                         const rct = this.$el.getBoundingClientRect();
                         const est = getComputedStyle(this.$el);
                         this.px = parseFloat(est.getPropertyValue('--passo-x')) || 42;
                         this.py = parseFloat(est.getPropertyValue('--passo-y')) || 42;
                         const c = Math.floor((e.clientX - rct.left) / this.px);
                         const r = Math.floor((e.clientY - rct.top) / this.py);
                         const chave = c + ':' + r;
                         if (chave === this.ultimo) return;
                         this.ultimo = chave;
                         const vizinhas = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, -1], [1, -1], [-1, 1]]
                             .sort(() => Math.random() - 0.5).slice(0, 3);
                         const grupo = [[0, 0], ...vizinhas].map(([dc, dr], i) => ({
                             id: ++this.seq, c: c + dc, r: r + dr, atraso: i * 90,
                         }));
                         const ids = grupo.map((g) => g.id);
                         this.rastro = [...this.rastro.slice(-4), ...grupo];
                         setTimeout(() => { this.rastro = this.rastro.filter((x) => ! ids.includes(x.id)); }, 1400);
                     },
                 }" x-init="ajustar(); new ResizeObserver(() => ajustar()).observe($el)"
                 @mousemove="rastreia($event)">

                <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                    <template x-for="ponto in rastro" :key="ponto.id">
                        <span class="pointer-events-none absolute top-0 left-0"
                              :style="`width:${px - 1}px;height:${py - 1}px;transform:translate(${ponto.c * px + 1}px,${ponto.r * py + 1}px)`">
                            <span class="celula-vira block h-full w-full rounded-[2px]"
                                  :style="`animation-delay:${ponto.atraso}ms`"></span>
                        </span>
                    </template>
                    @foreach ([[0, 1, '0s'], [1, 0, '1.2s'], [2, 2, '2.1s'], [1, 3, '3.3s'], [3, 1, '4.4s'], [0, 4, '5s']] as [$col, $lin, $atraso])
                        <span class="celula-viva"
                              style="right: calc(var(--passo-x, 42px) * {{ $col }} + 1px); bottom: calc(var(--passo-y, 42px) * {{ $lin }} + 1px); animation-delay: {{ $atraso }}"></span>
                    @endforeach
                </div>

                {{-- A campanha vigente veste o banner; sem campanha, texto fixo.
                     O controller so entrega campanha que passou no filtro da
                     vitrine: preco e fornecedor nao sobem para a pagina publica. --}}
                <span class="relative inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white/70 px-3 py-1 text-xs font-medium text-gray-700 dark:border-white/20 dark:bg-white/10 dark:text-white">
                    <span class="size-1.5 animate-pulse rounded-full bg-success-500"></span>
                    {{ $campanha->nome ?? 'Campanha de adesão aberta' }}
                </span>

                <h2 class="mx-auto mt-5 max-w-2xl text-3xl font-semibold text-gray-800 sm:text-4xl dark:text-white">
                    Comece a consultar ainda esta semana
                </h2>

                <p class="mx-auto mt-4 max-w-xl text-gray-600 dark:text-gray-400">
                    {{ $campanha->oferta ?? 'Novos clientes contam com condições especiais de adesão e uma proposta sob medida para o volume da sua empresa. Deixe seu contato e um consultor retorna ainda hoje.' }}
                </p>

                <button type="button" @click="aberto = 'campanha'"
                        class="mt-8 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-medium text-white transition hover:bg-brand-600">
                    Quero aproveitar a campanha
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M18 12H6"/>
                    </svg>
                </button>
            </div>
        </section>

        {{-- WhatsApp suspenso, discreto, no prumo do interruptor de tema. O
             assunto vai pre-escrito e nenhum dado pessoal entra na URL. --}}
        <a href="{{ Suporte::whatsapp('Quero conhecer a Avalia') }}" target="_blank" rel="noopener noreferrer"
           aria-label="Conversar no WhatsApp"
           class="fixed right-4 bottom-5 z-40 flex size-11 items-center justify-center rounded-full bg-success-500 text-white shadow-theme-lg opacity-40 transition hover:scale-110 hover:bg-success-600 hover:opacity-100">
            <svg class="size-6 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.09-.17.04-.31-.02-.43-.06-.13-.56-1.35-.77-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.2-.58.2-1.08.14-1.18-.06-.11-.22-.17-.47-.29Z"/>
            </svg>
        </a>

        <footer class="border-t border-gray-100 dark:border-gray-800">
            {{-- O aviso de uso responsavel que todo o mercado carrega: a
                 consulta serve a decisao de negocio do proprio contratante,
                 e e isso que a base legal cobre. --}}
            <p class="mx-auto w-full max-w-[87rem] px-6 pt-8 text-xs text-gray-400 dark:text-gray-500">
                As informações fornecidas destinam-se exclusivamente a apoiar decisões de
                negócio do próprio contratante, na finalidade de pesquisa de score e
                proteção ao crédito prevista na Lei 12.414/2011 e no art. 7º, X, da
                Lei 13.709/2018 (LGPD). A Avalia não concede empréstimos, não garante
                aprovação de crédito e não decide em nome de seus clientes. É vedado o
                repasse das informações ou seu uso para qualquer outra finalidade.
            </p>
            <div class="mx-auto flex w-full max-w-[87rem] flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('inicio') }}" aria-label="Início">
                    <x-avalia.logotipo :tamanho="28" one />
                </a>
                <p>© {{ now()->year }} Avalia · CNPJ 39.914.870/0001-01</p>
                <a class="flex items-center gap-1.5 hover:text-brand-500" href="mailto:comercial@avaliaone.com.br">
                    comercial@avaliaone.com.br
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <rect x="3" y="5.5" width="18" height="13" rx="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/>
                    </svg>
                </a>
            </div>
        </footer>

        {{-- Popup dos pilares: um overlay so, com os tres assuntos num trilho
             que desliza. A altura e unica por construcao (os slides dividem a
             mesma linha de flex), entao folhear nao muda o tamanho do card.
             Setinha, arrasto de mouse ou dedo, Esc e clique fora. --}}
        <div x-cloak x-show="pilares.includes(aberto)" x-transition.opacity.duration.500ms
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
             @click.self="aberto = null" role="dialog" aria-modal="true" aria-label="Sobre a Avalia">
            <div class="relative w-full max-w-4xl">
                <button type="button" x-show="idxPilar() > 0" @click="anteriorPilar()" aria-label="Assunto anterior"
                        class="absolute top-1/2 -left-11 z-10 hidden -translate-y-1/2 text-white transition hover:scale-125 sm:block">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button type="button" x-show="idxPilar() < 2" @click="proximoPilar()" aria-label="Próximo assunto"
                        class="absolute top-1/2 -right-11 z-10 hidden -translate-y-1/2 text-white transition hover:scale-125 sm:block">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button type="button" @click="aberto = null" aria-label="Fechar"
                        class="absolute -top-10 right-0 z-10 text-white transition hover:scale-125">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>

                {{-- O recorte do trilho: sem ele o card vizinho fica inteiro
                     visivel ao lado. A margem negativa com o mesmo padding
                     preserva a sombra do card dentro da area recortada. --}}
                <div class="entra-popup -m-6 touch-pan-y overflow-hidden p-6 select-none"
                     @pointerdown="comecaArrasto($event)" @pointermove="moveArrasto($event)"
                     @pointerup="soltaArrasto()" @pointercancel="soltaArrasto()" @pointerleave="soltaArrasto()">
                    <div class="flex items-stretch gap-8"
                         :class="inicioX === null ? 'transition-transform duration-700 ease-out' : 'transition-none'"
                         :style="`transform: translateX(calc(${-idxPilar()} * (100% + 2rem) + ${arrasto}px))`">
                        @foreach ($pilares as $chave => $pilar)
                            <div class="flex w-full shrink-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
                                <div class="grade-viva relative bg-gray-300/35 px-7 py-6 dark:bg-black/50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex size-14 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                                            <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $pilar['icone'] }}"/>
                                            </svg>
                                        </div>
                                        <x-avalia.logotipo :tamanho="26" one />
                                    </div>
                                    <h3 class="mt-5 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $pilar['titulo'] }}</h3>
                                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">{{ $pilar['detalhe'] }}</p>
                                </div>

                                <div class="flex flex-1 flex-col p-7">
                                    <ul class="mb-7 grid gap-3 sm:grid-cols-2">
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
                                       class="botao botao-primario mt-auto w-full" draggable="false">
                                        <svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Z"/>
                                        </svg>
                                        Falar com um consultor
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- O formulario da campanha. Os dados entram no nosso banco e a
             conversa comeca do nosso lado: nome e telefone sao dado pessoal e
             nao viajam em URL de WhatsApp. --}}
        <div x-cloak x-show="aberto === 'campanha'" x-transition.opacity.duration.500ms
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
             @click.self="aberto = null" role="dialog" aria-modal="true" aria-label="Pedido de contato">
            <div class="relative w-full max-w-md">
                <button type="button" @click="aberto = null" aria-label="Fechar"
                        class="absolute -top-10 right-0 z-10 text-white transition hover:scale-125">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>

            <div class="entra-popup max-h-[92vh] w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-7 shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <h3 class="text-xl font-semibold">Quase lá</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Deixe seu contato e um consultor retorna ainda hoje.
                    </p>
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
        </div>

        {{-- Confirmacao de que o pedido chegou. --}}
        <div x-cloak x-show="aberto === 'obrigado'" x-transition.opacity.duration.500ms
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
