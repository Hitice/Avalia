@props(['pagina'])

{{-- Paginacao curta: anterior, posicao, proxima.

     Sem a fila de numeros do Laravel de proposito. Ninguem procura a pagina 7 de
     uma lista de consultas: procura por filtro. Numero de pagina so serve para
     saber onde esta e para voltar uma. --}}

@if ($pagina->hasPages())
    <div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-4 border-t border-gray-100 px-6 py-4 dark:border-gray-800']) }}>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ $pagina->firstItem() }} a {{ $pagina->lastItem() }} de {{ $pagina->total() }}
        </span>

        <div class="flex items-center gap-2">
            @if ($pagina->onFirstPage())
                <span class="botao botao-secundario botao-sm opacity-40">Anterior</span>
            @else
                <x-avalia.botao variante="secundario" tamanho="sm" :href="$pagina->previousPageUrl()">Anterior</x-avalia.botao>
            @endif

            @if ($pagina->hasMorePages())
                <x-avalia.botao variante="secundario" tamanho="sm" :href="$pagina->nextPageUrl()">Próxima</x-avalia.botao>
            @else
                <span class="botao botao-secundario botao-sm opacity-40">Próxima</span>
            @endif
        </div>
    </div>
@endif
