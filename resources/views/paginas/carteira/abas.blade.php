{{-- Abas da carteira. Um controle so, incluido por todas as telas do vendedor,
     porque aba que cada tela desenha por conta propria e aba que sai da ordem
     na primeira que alguem editar. --}}

<x-avalia.segmentado
    class="mb-6"
    :atual="request()->path()"
    :itens="[
        'carteira' => ['rotulo' => 'Empresas', 'url' => route('carteira')],
        'carteira/consultas' => ['rotulo' => 'Consultas', 'url' => route('carteira.consultas')],
        'carteira/servicos' => ['rotulo' => 'Serviços', 'url' => route('carteira.servicos')],
        'carteira/simulacao' => ['rotulo' => 'Simulação', 'url' => route('carteira.simulacao')],
    ]" />
