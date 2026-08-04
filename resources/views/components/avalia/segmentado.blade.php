@props([
    'itens' => [],
    'atual' => null,
    'rotulo' => null,
])

{{-- Controle segmentado: um grupo de opcoes mutuamente exclusivas.

     Substitui as fileiras de pilhas soltas que a tela tinha. Ganhos:

     - largura minima igual em todos os segmentos, entao o grupo fica simetrico
       mesmo com rotulos de tamanhos diferentes ("Todos" x "Preço de venda");
     - a moldura unica comunica que as opcoes competem entre si, o que tres
       botoes separados nao comunicavam;
     - altura fixa, igual a dos botoes do resto do sistema.

     $itens: [chave => ['rotulo' => ..., 'url' => ...]] --}}

<div {{ $attributes->merge(['class' => 'inline-flex flex-col gap-1.5']) }}>
    @if ($rotulo)
        <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
            {{ $rotulo }}
        </span>
    @endif

    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-white/[0.03]">
        @foreach ($itens as $chave => $item)
            <a href="{{ $item['url'] }}"
               @if ((string) $atual === (string) $chave) aria-current="page" @endif
               class="{{ (string) $atual === (string) $chave
                   ? 'bg-brand-500 text-white shadow-sm'
                   : 'text-gray-600 hover:bg-white hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200' }} inline-flex h-9 min-w-28 items-center justify-center rounded-md px-4 text-sm font-medium transition">
                {{ $item['rotulo'] }}
            </a>
        @endforeach
    </div>
</div>
