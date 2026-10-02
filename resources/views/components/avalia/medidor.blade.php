@props([
    'tamanho' => 160,
    // As faixas acendem ate a fracao lida de --nivel (0 a 1, herdada por CSS)
    // e o ponteiro gira junto, sincronizados com a contagem da pontuacao.
    'porNivel' => false,
])

@php
    use App\Support\Marca;

    // Id proprio por instancia: dois medidores na mesma pagina nao podem
    // disputar o mesmo degrade.
    $id = 'medidor-'.uniqid();

    // A faixa n acende quando a leitura passa de (n-1)/4. O x100 faz o clamp
    // virar degrau: ou apagada, ou acesa, sem meio-tom.
    $acende = fn (int $n) => 'style="opacity: clamp(0, calc((var(--lido, 0) * 4 - '.($n - 1).') * 100), 1)"';
@endphp

{{-- O simbolo da marca em movimento: as quatro faixas do desenho do dono
     acendendo uma a uma conforme o ponteiro passa, e o ponteiro girando em
     volta do proprio eixo. Nasce apontando para a esquerda (nivel zero) e
     para na leitura.

     Por opacidade, e nao por recorte girando: clipPath nao aceita grupo nem
     transformacao animada com a mesma sorte em todo navegador. --}}
<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="{{ Marca::CAIXA }}"
    role="img" aria-label="Medidor de risco da Avalia One"
    {{ $attributes->merge(['class' => 'text-gray-900 dark:text-white']) }}>
    <defs>{!! Marca::degrades($id) !!}</defs>

    <g class="medidor-nivel-faixa">{!! Marca::faixas($id, $acende) !!}</g>

    <g class="medidor-nivel-ponteiro">{!! Marca::ponteiro('currentColor') !!}</g>
</svg>
