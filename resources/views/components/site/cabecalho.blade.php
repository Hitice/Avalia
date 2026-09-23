@props([
    'selo' => null,
    'titulo',
    // Para onde o selo leva, quando ele for um caminho de volta. O artigo do
    // blog usa isso para voltar a listagem sem precisar de um segundo link.
    'href' => null,
    // O desenho do assunto da pagina, em path de 24px. Vem por parametro
    // porque e a unica coisa que muda de uma pagina para outra: o resto da
    // faixa e identico, e e isso que faz o site parecer um so.
    'icone' => null,
])

{{--
    O topo de uma pagina do site.

    A superficie escura com a grade da marca, o selo do assunto, o titulo, o
    icone da pagina e o trilho de termos no pe. Repetido em nove paginas:
    escrito a mao em cada uma, o espacamento do titulo ja tinha saido diferente
    em duas delas.
--}}

<section class="superficie-escura">
    {{-- A faixa mede seis quadradinhos de altura: 6 x 42px, o passo da grade,
         como o herói da porta do dominio mede oito. Altura fixa e igual em
         todas: faixa que encolhe conforme o tamanho do titulo faz o cabecalho
         pular de lugar a cada troca de pagina, e quem navega entre elas sente
         o site inteiro balancar. --}}
    <div class="grade-viva-escura relative overflow-hidden lg:h-[252px]">
        {{-- A luz rosa no canto, a outra ponta do degrade da marca. Entra como
             luz, e nao como elemento: uma forma rosa desenhada competiria com
             o titulo, e a mancha so tira o azul da monotonia.

             Ela ja seguiu o cursor, e a faixa ficou pesada: um borrao de 384px
             reposicionado a cada movimento do mouse e repintura de tela cheia
             a 60 quadros, e o efeito nao pagava o custo. --}}
        <i class="brilho-rosa pointer-events-none absolute -top-24 -right-24 size-80 rounded-full blur-2xl" aria-hidden="true"></i>

        <div class="mx-auto flex h-full w-full max-w-[87rem] items-center gap-8 px-6 py-12 lg:py-0">
            <div class="min-w-0 flex-1">
                @if ($selo)
                    @if ($href)
                        <a href="{{ $href }}" class="selo selo-claro transition hover:bg-white/10">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 18l-6-6 6-6" />
                            </svg>
                            {{ $selo }}
                        </a>
                    @else
                        <span class="selo selo-claro">{{ $selo }}</span>
                    @endif
                @endif

                <h1 class="mt-4 max-w-3xl text-3xl font-semibold tracking-tight sm:text-title-sm lg:text-title-md">{{ $titulo }}</h1>

                @if (trim($slot) !== '')
                    <p class="mt-3 max-w-2xl leading-relaxed text-white/60">{{ $slot }}</p>
                @endif

                @isset($rodape)
                    <div class="mt-3">{{ $rodape }}</div>
                @endisset
            </div>

            {{-- O icone da pagina, na moldura rosa que acompanha a luz do
                 canto. Escondido no celular: ali a largura e do titulo, e um
                 simbolo decorativo roubando meia linha dele nao paga o
                 espaco. --}}
            @if ($icone)
                <span class="hidden size-16 shrink-0 items-center justify-center rounded-2xl border border-theme-pink-500/30 bg-theme-pink-500/10 text-theme-pink-500 sm:inline-flex"
                      aria-hidden="true">
                    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}" />
                    </svg>
                </span>
            @endif
        </div>

        {{-- A linha no pe da faixa. O pulso corre por ela ate 60% da largura e
             entao sobe pela grade em degraus de 42px, apagando antes do topo e
             antes do icone da direita: e o mesmo movimento do diagrama do
             herói, mas aqui ele usa a textura da propria faixa como caminho. --}}
        <div class="absolute inset-x-0 bottom-0 h-px bg-white/10" aria-hidden="true">
            <i class="risca-degrau absolute inset-0 bg-brand-400/70" style="--atraso: 0.4s"></i>
            <i class="corre-degrau absolute size-1.5 -translate-x-1/2 translate-y-1/2 rounded-full bg-success-400 shadow-[0_0_10px_2px_rgb(50_213_131/0.6)]"
               style="--atraso: 0.4s"></i>
        </div>
    </div>

    <x-site.trilho />
</section>
