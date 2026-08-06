@props(['tamanho' => 160])

{{-- O simbolo da marca em movimento: o ponteiro parte do fundo da escala e
     assenta na faixa alta, com o arco se desenhando junto. E a logo fazendo o
     que ela promete, uma leitura de risco chegando ao resultado.

     Mesmo desenho do logotipo, so que animado. Se a marca mudar, muda nos dois
     lugares, e e por isso que o path e identico. --}}

<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" fill="none"
    role="img" aria-label="Medidor de risco da Avalia" {{ $attributes }}>
    {{-- Escala --}}
    <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="currentColor"
        class="text-gray-300 dark:text-gray-700" stroke-width="3" stroke-linecap="round" />
    {{-- Faixa atingida, desenhada durante a leitura --}}
    <path d="M4.5 22.5A11.5 11.5 0 0 1 16 11" stroke="currentColor"
        class="text-brand-500 medidor-faixa" stroke-width="3" stroke-linecap="round" />
    {{-- Ponteiro, varrendo ate a leitura --}}
    <g class="medidor-ponteiro">
        <path d="M16 22.5 22.3 14.6" stroke="currentColor" class="text-brand-500"
            stroke-width="3" stroke-linecap="round" />
    </g>
    <circle cx="16" cy="22.5" r="2.6" fill="currentColor" class="text-brand-500" />
</svg>
