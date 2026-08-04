{{-- Navegacao do modulo. Recebe $atual por @include. --}}

<x-avalia.segmentado
    class="mb-6"
    :atual="$atual"
    :itens="[
        'planos' => ['rotulo' => 'Planos', 'url' => route('catalogo.index')],
        'catalogo' => ['rotulo' => 'Catalogo', 'url' => route('catalogo.tabela')],
        'servicos' => ['rotulo' => 'Servicos', 'url' => route('catalogo.servicos.index')],
    ]" />
