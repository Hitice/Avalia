@props([
    'tamanho' => 160,
    // A faixa sobe ate a fracao lida de --nivel (0 a 1, herdada por CSS) e o
    // ponteiro gira junto, sincronizados com a contagem da pontuacao.
    'porNivel' => false,
])

@php
    // Id proprio por instancia: dois medidores na mesma pagina nao podem
    // disputar o mesmo gradiente.
    $grad = 'medidor-'.uniqid();
@endphp

{{-- O simbolo da marca em movimento. A escala e o semicirculo inteiro:
     pathLength=100 deixa a conta do CSS em porcentagem, e o degrade rosa-azul
     cobre a faixa da esquerda para a direita, o mesmo da pontuacao.

     O ponteiro e uma ponta solta: triangulo afilado girando em volta do eixo,
     com folga do circulo central, mirando o fim do enchimento. Nasce deitado a
     esquerda (nivel zero) e gira ate a leitura. --}}

<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" fill="none"
    role="img" aria-label="Medidor de risco da Avalia" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $grad }}" x1="4.5" y1="22.5" x2="27.5" y2="22.5" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="var(--color-theme-pink-500)" />
            <stop offset="1" stop-color="var(--color-brand-500)" />
        </linearGradient>
        <linearGradient id="{{ $grad }}-p" x1="12.2" y1="22.5" x2="7.0" y2="22.5" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="var(--color-theme-pink-500)" />
            <stop offset="1" stop-color="var(--color-brand-500)" />
        </linearGradient>
    </defs>

    {{-- Escala --}}
    <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="currentColor"
        class="text-gray-300 dark:text-gray-700" stroke-width="3" stroke-linecap="round" />

    {{-- Faixa que sobe ate o nivel, no degrade de bureau --}}
    <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" pathLength="100"
        stroke="url(#{{ $grad }})" class="medidor-nivel-faixa"
        stroke-width="3" stroke-linecap="round" />

    {{-- Ponta solta girando em volta do eixo, sem encostar nele --}}
    <g class="medidor-nivel-ponteiro">
        <path d="M7.0 22.5 12.2 21.7 12.2 23.3 Z" fill="url(#{{ $grad }}-p)"
            stroke="url(#{{ $grad }}-p)" stroke-width="0.7" stroke-linejoin="round" />
    </g>

    <circle cx="16" cy="22.5" r="2.6" fill="var(--color-theme-pink-500)" />
</svg>
