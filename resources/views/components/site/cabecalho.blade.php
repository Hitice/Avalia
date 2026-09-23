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
    {{-- `--subida` e o ponto onde o pulso para de correr reto e comeca a
         subir a grade: 40% da largura mais seis quadradinhos de 42px. Mora
         aqui porque as tres pecas do caminho a leem. --}}
    <div class="grade-viva-escura relative overflow-hidden lg:h-[252px]" style="--subida: calc(40% + 252px)">
        {{-- A luz rosa no canto, a outra ponta do degrade da marca. Entra como
             luz, e nao como elemento: uma forma rosa desenhada competiria com
             o titulo, e a mancha so tira o azul da monotonia.

             Ela ja seguiu o cursor, e a faixa ficou pesada: um borrao de 384px
             reposicionado a cada movimento do mouse e repintura de tela cheia
             a 60 quadros, e o efeito nao pagava o custo. --}}
        <i class="brilho-rosa pointer-events-none absolute -top-24 -right-24 size-80 rounded-full blur-2xl" aria-hidden="true"></i>

        {{-- A folga de 76px no topo e o que a ilha ocupa: 12px de margem mais
             os 60px dela, e uma sobra. Sem ela o titulo nasceria por baixo do
             cabecalho. A altura da faixa nao muda, entao a grade continua
             fechando em seis quadradinhos. --}}
        <div class="mx-auto flex h-full w-full max-w-[87rem] items-center gap-8 px-6 pt-[76px] pb-12 lg:pb-0">
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
        {{-- O trecho reto do caminho, do pe da faixa ate os 60% onde a escada
             comeca. --}}
        <div class="absolute inset-x-0 bottom-0 h-px bg-white/10" aria-hidden="true">
            <i class="risca-degrau absolute inset-y-0 left-0 hidden bg-brand-400/70 lg:block" style="--atraso: 0.4s"></i>
            {{-- Tres em fila, e nao uma so: a da frente puxa e as outras duas
                 vem atras, menores e mais apagadas. E a de frente que o traco
                 acompanha; as outras sao o rastro dela. --}}
            <i class="corre-degrau absolute hidden size-1.5 -translate-x-1/2 translate-y-1/2 rounded-full bg-success-400 shadow-[0_0_10px_2px_rgb(50_213_131/0.6)] lg:block"
               style="--atraso: 0.4s"></i>
            <i class="corre-degrau absolute hidden size-1.5 -translate-x-1/2 translate-y-1/2 rounded-full bg-success-400/60 lg:block"
               style="--atraso: 0.58s"></i>
            <i class="corre-degrau absolute hidden size-1 -translate-x-1/2 translate-y-1/2 rounded-full bg-success-400/35 lg:block"
               style="--atraso: 0.76s"></i>
        </div>

        {{-- A escada. A caixa tem o tamanho exato do desenho, 224 por 168, e o
             viewBox tambem: sem esticar, o tracejado e medido nas mesmas
             unidades em que a bolinha anda, e os dois chegam juntos a cada
             canto. Esticado, eles descolavam nos trechos horizontais.

             So no desktop: a escada precisa dos 252px de altura da faixa, que
             o celular nao tem. --}}
        <svg class="pointer-events-none absolute bottom-0 hidden h-[168px] w-[224px] text-brand-400 lg:block" style="left: var(--subida)"
             viewBox="0 0 224 168" aria-hidden="true">
            <path class="desenha-degrau" pathLength="100" style="--atraso: 0.4s"
                  d="M0 168V126H56V84H112V42H168V0H224"
                  fill="none" stroke="currentColor" stroke-opacity="0.7" stroke-width="1.5" />
        </svg>
    </div>

    <x-site.trilho />
</section>
