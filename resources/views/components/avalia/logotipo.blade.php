@props([
    'tamanho' => 32,
    // Tamanho do wordmark. Sem ele, vale o padrao fixo: mexer na proporcao
    // global mudaria toda tela que ja usa a marca.
    'texto' => null,
    'somenteIcone' => false,
    // Sobre fundo escuro da marca o nome sai branco direto, sem depender do
    // tema da pagina. Antes isso era um seletor arbitrario no ponto de uso,
    // que quebrava calado se a estrutura interna daqui mudasse.
    'claro' => false,
])

{{--
    Marca da Avalia 360.

    O icone e um arco de medidor com o ponteiro apontando para a faixa alta,
    a leitura de risco que o produto entrega. Fica em azul da marca; o arco de
    fundo em cinza claro marca a escala sem competir com o ponteiro.

    Sem preenchimento solido atras: o simbolo respira sobre fundo claro ou
    escuro sem precisar de duas versoes.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" fill="none"
        role="img" aria-label="Avalia 360" class="shrink-0">
        {{-- Escala --}}
        <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="currentColor"
            class="text-gray-300 dark:text-gray-700" stroke-width="3" stroke-linecap="round" />
        {{-- Faixa atingida --}}
        <path d="M4.5 22.5A11.5 11.5 0 0 1 16 11" stroke="currentColor"
            class="text-brand-500" stroke-width="3" stroke-linecap="round" />
        {{-- Ponteiro --}}
        <path d="M16 22.5 22.3 14.6" stroke="currentColor" class="text-brand-500"
            stroke-width="3" stroke-linecap="round" />
        <circle cx="16" cy="22.5" r="2.6" fill="currentColor" class="text-brand-500" />
    </svg>

    @unless ($somenteIcone)
        {{-- O lockup e um so: "Avalia" no azul da marca e "360" em cinza, como
             no logotipo impresso. Antes o numero era opcional e o nome curto
             valia dentro do produto, o que deixava duas marcas circulando ao
             mesmo tempo. --}}
        <span class="leading-none font-semibold tracking-tight {{ $claro ? 'text-white' : 'text-brand-600 dark:text-white/90' }}"
              style="font-size: {{ $texto ?? '1.35rem' }}">
            Avalia<span class="ml-1 text-gray-300 dark:text-gray-400">360</span>
        </span>
    @endunless
</span>
