@props([
    'variante' => 'primario',
    'tamanho' => 'md',
    'href' => null,
])

{{-- Botao unico do sistema.

     Existia py-2, py-2.5 e py-3 espalhados pelas telas, o que deixava dois
     botoes lado a lado com alturas diferentes. Aqui ha um tamanho so por
     contexto, e link e botao ficam identicos: o operador nao deve perceber que
     "Novo plano" e uma navegacao e "Salvar" e um envio. --}}

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg text-sm font-medium transition whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50';

    $tamanhos = [
        'sm' => 'h-9 px-3',
        'md' => 'h-10 px-4',
    ];

    $variantes = [
        'primario' => 'bg-brand-500 text-white hover:bg-brand-600',
        'secundario' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]',
        'perigo' => 'border border-error-300 bg-white text-error-600 hover:bg-error-50 dark:border-error-500/40 dark:bg-transparent dark:text-error-400',
    ];

    $classe = implode(' ', [
        $base,
        $tamanhos[$tamanho] ?? $tamanhos['md'],
        $variantes[$variante] ?? $variantes['primario'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classe]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classe]) }}>{{ $slot }}</button>
@endif
