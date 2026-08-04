@props([
    'variante' => 'primario',
    'tamanho' => 'md',
    'href' => null,
])

{{-- Botao unico do sistema. Estilo vive em app.css, como o do tema. --}}

@php
    $classe = trim(sprintf(
        'botao botao-%s %s',
        in_array($variante, ['primario', 'secundario'], true) ? $variante : 'primario',
        $tamanho === 'sm' ? 'botao-sm' : '',
    ));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classe]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classe]) }}>{{ $slot }}</button>
@endif
