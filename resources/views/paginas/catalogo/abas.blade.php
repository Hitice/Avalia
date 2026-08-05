{{-- Navegacao do modulo. Recebe $atual por @include. --}}

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <x-avalia.segmentado
        :atual="$atual"
        :itens="[
            'planos' => ['rotulo' => 'Planos', 'url' => route('catalogo.index')],
            'catalogo' => ['rotulo' => 'Catalogo', 'url' => route('catalogo.tabela')],
            'servicos' => ['rotulo' => 'Servicos', 'url' => route('catalogo.servicos.index')],
        ]" />

    {{-- Os dois botoes tem o mesmo tamanho e o mesmo peso. O input de arquivo
         fica escondido atras do label porque o texto nativo do navegador
         ("Escolher arquivo / Nenhum escolhido") nao cabe no desenho e nao diz
         nada: quem clica ja sabe que vai escolher um arquivo. --}}
    <form method="POST" action="{{ route('catalogo.planilha.importar') }}" enctype="multipart/form-data"
          class="flex items-center gap-2"
          x-data="{ enviando: false }">
        @csrf

        <x-avalia.botao variante="secundario" tamanho="sm" :href="route('catalogo.planilha.exportar')">
            Exportar
        </x-avalia.botao>

        <label class="botao botao-secundario botao-sm cursor-pointer">
            <span x-text="enviando ? 'Enviando...' : 'Importar'">Importar</span>
            <input type="file" name="planilha" accept=".xlsx,.csv" class="hidden"
                   @change="enviando = true; $el.form.submit()">
        </label>
    </form>
</div>

@error('planilha')
    <div class="aviso aviso-erro mb-6">{{ $message }}</div>
@enderror
