@props([
    'tamanho' => 32,
    'somenteIcone' => false,
    // Sobre fundo escuro da marca o nome sai branco direto, sem depender do
    // tema da pagina. Antes isso era um seletor arbitrario no ponto de uso,
    // que quebrava calado se a estrutura interna daqui mudasse.
    'claro' => false,
    // Com o sufixo do dominio em cinza: Avaliaone. E o nome publico; o
    // wordmark curto continua valendo dentro do produto.
    'one' => false,
])

{{--
    Marca da Avalia.

    O icone e um arco de medidor com o ponteiro apontando para a faixa alta,
    a leitura de risco que o produto entrega. Fica em azul da marca; o arco de
    fundo em cinza claro marca a escala sem competir com o ponteiro.

    Sem preenchimento solido atras: o simbolo respira sobre fundo claro ou
    escuro sem precisar de duas versoes.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" fill="none"
        role="img" aria-label="Avalia" class="shrink-0">
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
        {{-- Sem diretiva no meio do texto: "Avalia@if" colado nao compila como
             Blade (limite de palavra), mas o @endif compila, e sobrava um
             endif orfao derrubando toda pagina com a logo. O ternario nao tem
             esse problema. --}}
        <span class="text-[1.35rem] leading-none font-semibold tracking-tight {{ $claro ? 'text-white' : 'text-gray-800 dark:text-white/90' }}">
            Avalia{{ '' }}<span class="text-gray-400 dark:text-gray-500">{{ $one ? 'one' : '' }}</span>
        </span>
    @endunless
</span>
