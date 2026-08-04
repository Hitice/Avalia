@props([
    'itens' => [],
    'atual' => null,
    'rotulo' => null,
])

{{-- Controle segmentado: opcoes mutuamente exclusivas numa moldura so.

     Largura minima igual em todos os segmentos, entao o grupo fica simetrico
     mesmo com rotulos de tamanhos diferentes.

     $itens: [chave => ['rotulo' => ..., 'url' => ...]] --}}

<div {{ $attributes->merge(['class' => 'inline-flex flex-col gap-1.5']) }}>
    @if ($rotulo)
        <span class="rotulo-grupo">{{ $rotulo }}</span>
    @endif

    <div class="segmento-grupo">
        @foreach ($itens as $chave => $item)
            @php $ativo = (string) $atual === (string) $chave; @endphp

            <a href="{{ $item['url'] }}"
               @if ($ativo) aria-current="page" @endif
               class="segmento {{ $ativo ? 'segmento-ativo' : 'segmento-inativo' }}">
                {{ $item['rotulo'] }}
            </a>
        @endforeach
    </div>
</div>
