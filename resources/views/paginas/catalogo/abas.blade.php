{{-- Navegacao do modulo. Recebe $atual por @include. --}}

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <x-avalia.segmentado
        :atual="$atual"
        :itens="[
            'planos' => ['rotulo' => 'Planos', 'url' => route('catalogo.index')],
            'catalogo' => ['rotulo' => 'Catalogo', 'url' => route('catalogo.tabela')],
            'servicos' => ['rotulo' => 'Servicos', 'url' => route('catalogo.servicos.index')],
        ]" />

    <div class="flex flex-wrap items-center gap-2">
        <x-avalia.botao variante="secundario" tamanho="sm" :href="route('catalogo.planilha.exportar')">
            Exportar planilha
        </x-avalia.botao>

        {{-- Importar e um upload, entao o form fica aqui e nao numa tela propria:
             quem exporta para ajustar no Excel volta pelo mesmo lugar. --}}
        <form method="POST" action="{{ route('catalogo.planilha.importar') }}" enctype="multipart/form-data"
              class="flex items-center gap-2">
            @csrf
            <input type="file" name="planilha" accept=".xlsx,.csv" required
                   class="max-w-56 text-xs text-gray-500 file:mr-2 file:rounded-lg file:border file:border-gray-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 dark:text-gray-400 dark:file:border-gray-700 dark:file:bg-white/[0.03] dark:file:text-gray-300">
            <x-avalia.botao variante="secundario" tamanho="sm">Importar</x-avalia.botao>
        </form>
    </div>
</div>

@error('planilha')
    <div class="aviso aviso-erro mb-6">{{ $message }}</div>
@enderror
