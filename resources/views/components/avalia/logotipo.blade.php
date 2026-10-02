@props([
    'tamanho' => 32,
    // Tamanho do wordmark. Sem ele, vale o padrao fixo: mexer na proporcao
    // global mudaria toda tela que ja usa a marca.
    'texto' => null,
    'somenteIcone' => false,
    // Sobre fundo escuro da marca o nome sai como no desenho original, sem
    // depender do tema da pagina. Antes isso era um seletor arbitrario no
    // ponto de uso, que quebrava calado se a estrutura interna daqui mudasse.
    'claro' => false,
    // Qual das quatro marcas esta assinando a tela: a casa ou um dos tres
    // produtos. Vem por parametro porque o mesmo desenho serve a todas, e so
    // o sufixo muda.
    'marca' => 'casa',
])

@php
    use App\Support\Empresa;
    use App\Support\Marca;

    // O sufixo de cada produto, derivado da marca cadastrada: "Avalia One"
    // menos o "Avalia" da casa sobra "One". Assim trocar o nome comercial de
    // um produto e mexer em config/empresa.php, e nao caçar o sufixo escrito a
    // mao dentro de um componente.
    $completa = match ($marca) {
        'credito' => Empresa::marcaCredito(),
        'cobranca' => Empresa::marcaCobranca(),
        'vendas' => Empresa::marcaVendas(),
        default => Empresa::marca(),
    };

    $sufixo = trim(substr($completa, strlen(Empresa::marca())));

    // Id proprio por instancia: a sidebar imprime a marca duas vezes, uma
    // escondida, e degrade referenciado em elemento escondido nao pinta.
    $prefixo = 'marca-'.uniqid();
@endphp

{{--
    Marca da Avalia, do desenho do dono (public/marca/avaliaone.svg): o arco do
    medidor em magenta e o ponteiro na faixa alta. O tracado mora em
    App\Support\Marca; aqui so a cor do ponteiro, que no desenho e branco e
    sobre fundo claro sumiria.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="{{ Marca::CAIXA }}"
        role="img" aria-label="{{ trim(Empresa::marca().' '.$sufixo) }}"
        class="shrink-0 {{ $claro ? 'text-white' : 'text-gray-900 dark:text-white' }}">
        {!! Marca::arco($prefixo) !!}{!! Marca::ponteiro('currentColor') !!}
    </svg>

    @unless ($somenteIcone)
        {{-- O lockup e um so: o nome da casa no magenta do desenho e, quando a
             tela pertence a um produto, o sufixo dele em cinza ao lado, como o
             "One" do original. Sobre fundo claro o cinza do desenho sumiria,
             por isso ele escurece. --}}
        <span class="leading-none font-semibold tracking-tight"
              style="font-size: {{ $texto ?? '1.35rem' }}">
            <span class="bg-linear-to-tr from-marca-escura to-marca-clara bg-clip-text text-transparent">{{ Empresa::marca() }}</span>{{-- O espaco antes do sufixo e escrito a mao porque o Blade come o
                 que houver entre as diretivas: sem ele a marca sai
                 "AvaliaOne", grudada, que e outro nome. --}}@if ($sufixo !== '')<span class="{{ $claro ? 'text-gray-200' : 'text-gray-400 dark:text-gray-200' }}">&nbsp;{{ $sufixo }}</span>@endif
        </span>
    @endunless
</span>
