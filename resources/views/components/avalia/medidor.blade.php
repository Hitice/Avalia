@props([
    'tamanho' => 160,
    // Uma leitura so (login) ou em ciclo, refazendo a leitura a cada consulta
    // do carrossel (pagina inicial).
    'vivo' => false,
])

@php
    // Id proprio por instancia: dois medidores na mesma pagina nao podem
    // disputar o mesmo gradiente.
    $grad = 'medidor-'.uniqid();
@endphp

{{-- O simbolo da marca em movimento: o ponteiro parte do fundo da escala e
     assenta na faixa alta, com o arco se desenhando junto. E a logo fazendo o
     que ela promete, uma leitura de risco chegando ao resultado.

     O ponteiro leva o degrade de bureau, do rosa ao azul, o mesmo da
     pontuacao: e ele que "escreve" o numero, entao os dois falam a mesma cor.
     O gradiente e definido dentro do grupo que gira, e gira junto.

     Mesmo desenho do logotipo, so que animado. Se a marca mudar, muda nos dois
     lugares, e e por isso que o path e identico. --}}

<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" fill="none"
    role="img" aria-label="Medidor de risco da Avalia" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $grad }}" x1="16" y1="22.5" x2="22.3" y2="14.6" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="var(--color-theme-pink-500)" />
            <stop offset="1" stop-color="var(--color-brand-500)" />
        </linearGradient>
    </defs>
    {{-- Escala --}}
    <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="currentColor"
        class="text-gray-300 dark:text-gray-700" stroke-width="3" stroke-linecap="round" />
    {{-- Faixa atingida, desenhada durante a leitura --}}
    <path d="M4.5 22.5A11.5 11.5 0 0 1 16 11" stroke="currentColor"
        class="text-brand-500 {{ $vivo ? 'medidor-faixa-viva' : 'medidor-faixa' }}"
        stroke-width="3" stroke-linecap="round" />
    {{-- Ponteiro, varrendo ate a leitura --}}
    <g class="{{ $vivo ? 'medidor-ponteiro-vivo' : 'medidor-ponteiro' }}">
        <path d="M16 22.5 22.3 14.6" stroke="url(#{{ $grad }})"
            stroke-width="3" stroke-linecap="round" />
    </g>
    <circle cx="16" cy="22.5" r="2.6" fill="var(--color-theme-pink-500)" />
</svg>
