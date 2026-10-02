@props(['titulo' => null, 'subtitulo' => null, 'rotulo' => null])

{{-- O cabecalho de toda tela do sistema: titulo, uma linha de apoio (ou um
     rotulo em caixa alta, para a tela que mostra um periodo) e as acoes a
     direita. Uma moldura so para 54 telas, que antes a copiavam com tres
     alinhamentos diferentes. --}}
<div {{ $attributes->class(['mb-6 flex flex-wrap items-end justify-between gap-3']) }}>
    <div>
        <h1 class="titulo-pagina">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="subtitulo-pagina">{{ $subtitulo }}</p>
        @endif
        @if ($rotulo)
            <p class="rotulo-grupo mt-1">{{ $rotulo }}</p>
        @endif
    </div>

    @if (trim($slot) !== '')
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
