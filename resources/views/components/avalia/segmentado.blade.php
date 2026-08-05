@props([
    'itens' => [],
    'atual' => null,
    'rotulo' => null,
])

{{-- Controle segmentado: opcoes mutuamente exclusivas numa moldura so.

     Largura minima igual em todos os segmentos, entao o grupo fica simetrico
     mesmo com rotulos de tamanhos diferentes.

     $itens: [chave => ['rotulo' => ..., 'url' => ..., 'travado' => bool, 'titulo' => ...]]

     Segmento travado vira <span>: continua no grupo, para quem olha saber que a
     opcao existe, mas nao navega e nao entra na ordem de tabulacao. --}}

<div {{ $attributes->merge(['class' => 'inline-flex flex-col gap-1.5']) }}>
    @if ($rotulo)
        <span class="rotulo-grupo">{{ $rotulo }}</span>
    @endif

    <div class="segmento-grupo">
        @foreach ($itens as $chave => $item)
            @php
                $ativo = (string) $atual === (string) $chave;
                $travado = $item['travado'] ?? false;
            @endphp

            @if ($travado)
                <span class="segmento segmento-travado"
                      @if (! empty($item['titulo'])) title="{{ $item['titulo'] }}" @endif>
                    <x-avalia.cadeado :titulo="$item['titulo'] ?? 'Indisponivel'" />
                    {{ $item['rotulo'] }}
                </span>
            @else
                <a href="{{ $item['url'] }}"
                   @if ($ativo) aria-current="page" @endif
                   class="segmento {{ $ativo ? 'segmento-ativo' : 'segmento-inativo' }}">
                    {{ $item['rotulo'] }}
                </a>
            @endif
        @endforeach
    </div>
</div>
