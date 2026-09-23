@props([
    'selo' => null,
    'titulo',
    // Para onde o selo leva, quando ele for um caminho de volta. O artigo do
    // blog usa isso para voltar a listagem sem precisar de um segundo link.
    'href' => null,
])

{{--
    O topo de uma pagina do site.

    A superficie escura com a grade da marca, o selo do assunto e o titulo.
    Repetido em nove paginas: escrito a mao em cada uma, o espacamento do
    titulo ja tinha saido diferente em duas delas.
--}}

<section class="superficie-escura grade-viva-escura">
    <div class="mx-auto w-full max-w-[87rem] px-6 py-16 lg:py-20">
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

        <h1 class="mt-6 max-w-3xl text-title-sm font-semibold tracking-tight lg:text-title-md">{{ $titulo }}</h1>

        @if (trim($slot) !== '')
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/60">{{ $slot }}</p>
        @endif

        @isset($rodape)
            <div class="mt-5">{{ $rodape }}</div>
        @endisset
    </div>
</section>
