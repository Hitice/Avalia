@props([
    'tamanho' => 160,
    // Com nivel, o medidor vira instrumento: a faixa sobe ate a fracao lida de
    // --nivel (0 a 1, herdada por CSS) e o ponteiro gira junto, sincronizados
    // com a contagem da pontuacao. Sem nivel, e a leitura fixa do login.
    'porNivel' => false,
])

@php
    // Id proprio por instancia: dois medidores na mesma pagina nao podem
    // disputar o mesmo gradiente.
    $grad = 'medidor-'.uniqid();
@endphp

{{-- O simbolo da marca em movimento. No modo por nivel, a escala e o
     semicirculo inteiro: pathLength=100 deixa a conta do CSS em porcentagem, e
     o degrade rosa-azul cobre a faixa da esquerda para a direita, o mesmo da
     pontuacao. O ponteiro nasce deitado a esquerda (nivel zero) e gira ate a
     leitura. --}}

<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" fill="none"
    role="img" aria-label="Medidor de risco da Avalia" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $grad }}" x1="4.5" y1="22.5" x2="27.5" y2="22.5" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="var(--color-theme-pink-500)" />
            <stop offset="1" stop-color="var(--color-brand-500)" />
        </linearGradient>
        <linearGradient id="{{ $grad }}-p" x1="16" y1="22.5" x2="6.2" y2="22.5" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="var(--color-theme-pink-500)" />
            <stop offset="1" stop-color="var(--color-brand-500)" />
        </linearGradient>
    </defs>

    {{-- Escala --}}
    <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="currentColor"
        class="text-gray-300 dark:text-gray-700" stroke-width="3" stroke-linecap="round" />

    @if ($porNivel)
        {{-- Faixa que sobe ate o nivel, no degrade de bureau --}}
        <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" pathLength="100"
            stroke="url(#{{ $grad }})" class="medidor-nivel-faixa"
            stroke-width="3" stroke-linecap="round" />
        {{-- Ponteiro deitado a esquerda, girando ate a leitura --}}
        <g class="medidor-nivel-ponteiro">
            <path d="M16 22.5 6.2 22.5" stroke="url(#{{ $grad }}-p)"
                stroke-width="3" stroke-linecap="round" />
        </g>
    @else
        {{-- Leitura fixa do login: faixa ate o topo, ponteiro assentado --}}
        <path d="M4.5 22.5A11.5 11.5 0 0 1 16 11" stroke="currentColor"
            class="text-brand-500 medidor-faixa" stroke-width="3" stroke-linecap="round" />
        <g class="medidor-ponteiro">
            <path d="M16 22.5 22.3 14.6" stroke="url(#{{ $grad }}-p)"
                stroke-width="3" stroke-linecap="round" />
        </g>
    @endif

    <circle cx="16" cy="22.5" r="2.6" fill="var(--color-theme-pink-500)" />
</svg>
