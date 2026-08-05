@props([
    'variante' => 'primario',
    'tamanho' => 'md',
    'href' => null,
])

{{-- Botao unico do sistema. Estilo vive em app.css, como o do tema.

     Os nomes de classe aparecem inteiros de proposito. Montar com sprintf
     esconde a string do scanner do Tailwind, que so gera o que consegue ler no
     codigo: "botao-primario" nunca chegou ao CSS e o botao saiu sem cor, preto
     sobre o fundo escuro. --}}

@php
    $variantes = [
        'primario' => 'botao-primario',
        'secundario' => 'botao-secundario',
    ];

    $classe = implode(' ', array_filter([
        'botao',
        $variantes[$variante] ?? 'botao-primario',
        $tamanho === 'sm' ? 'botao-sm' : null,
    ]));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classe]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classe]) }}>{{ $slot }}</button>
@endif
