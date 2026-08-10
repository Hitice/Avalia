@props(['rotulo', 'valor', 'href' => null, 'tom' => null, 'ajuda' => null])

{{-- Cartao de indicador da visao geral.

     Todo numero da visao geral gera a mesma pergunta seguinte ("de onde saiu
     isso?"), e a resposta ficava a dois ou tres cliques de menu. Aqui o proprio
     numero e o caminho.

     Vira <a> quando ha destino e continua <div> quando nao ha, porque link que
     leva a 403 (o financeiro exige permissao propria) ensina o operador a
     ignorar o cartao. Um componente so, para os dois casos ficarem identicos na
     tela e a diferenca ser so o clique. --}}
@php
    $tag = $href ? 'a' : 'div';
    $tomDoValor = $tom ?: 'text-gray-800 dark:text-white/90';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['cartao p-5', 'cartao-link' => (bool) $href]) }}>
    <span class="rotulo-grupo block">{{ $rotulo }}</span>
    <span class="mt-1 block text-2xl font-semibold tabular-nums {{ $tomDoValor }}">{{ $valor }}</span>

    @if ($ajuda)
        <span class="ajuda-campo">{{ $ajuda }}</span>
    @endif

    {{ $slot }}
</{{ $tag }}>
