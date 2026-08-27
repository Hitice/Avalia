@extends('layouts.app', ['title' => 'Leads'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Leads</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A base de prospecção e quem está trabalhando cada pedaço dela.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Exporta o recorte que está na tela: o filtro inteiro vai na
                 query string, então o link já carrega a escolha. --}}
            <x-avalia.botao variante="secundario" :href="route('leads.planilha', request()->query())">
                Exportar
            </x-avalia.botao>

            <x-avalia.botao :href="route('leads.criar')">Novo lead</x-avalia.botao>
        </div>
    </div>

    @if ($quantidadeRemovidos > 0 || $removidos)
        <div class="mb-6">
            <x-avalia.segmentado
                :atual="$removidos ? 'removidos' : 'base'"
                :itens="[
                    'base' => ['rotulo' => 'Na base', 'url' => route('leads.index')],
                    'removidos' => ['rotulo' => 'Removidos ('.$quantidadeRemovidos.')', 'url' => route('leads.index', ['removidos' => 1])],
                ]" />
        </div>
    @endif

    @include('paginas.catalogo.avisos')

    {{-- Os números do recorte, e não da base inteira: quem está montando uma
         distribuição precisa saber quantos leads ela alcança e quantos deles
         têm por onde ligar. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="No recorte" :valor="number_format($noRecorte, 0, ',', '.')"
                                   :ajuda="$noRecorte === $naBase ? 'A base inteira' : 'De '.number_format($naBase, 0, ',', '.').' na base'" />
        <x-avalia.cartao-indicador rotulo="Com telefone" :valor="number_format($comTelefone, 0, ',', '.')" ajuda="Dá para ligar hoje" />
        <x-avalia.cartao-indicador rotulo="Com e-mail" :valor="number_format($comEmail, 0, ',', '.')" ajuda="Dá para escrever hoje" />
        <x-avalia.cartao-indicador rotulo="Sem vendedor" :valor="number_format($semVendedor, 0, ',', '.')" ajuda="Parados, à espera de distribuição" />
    </div>

    <x-avalia.filtro-leads :acao="route('leads.index')" :vendedores="$vendedores" :ufs="$ufs"
                           :cidades="$cidades" :origens="$origens" :escolha="$escolha" />

    {{-- A tabela inteira dentro de um formulário, para a seleção das linhas
         virar ação em lote.

         O formulário posta para a URL COM a query string: é ela que o servidor
         relê quando a ação é sobre o recorte inteiro. Sem isso, "passar
         Uberlândia para a Ana" pararia nos cinquenta leads da página aberta. --}}
    <form method="POST" action="{{ route('leads.lote', request()->query()) }}"
          x-data="{ marcados: [], escopo: 'marcados', vendedor: '' }">
        @csrf
        <input type="hidden" name="escopo" :value="escopo">

        <div class="cartao overflow-hidden">
            {{-- A barra fica sempre visível quando há linhas, e não só depois da
                 primeira marcação: é aqui que mora "selecionar o filtro inteiro",
                 e escondê-la até alguém marcar uma caixa esconderia justamente o
                 caminho de quem quer distribuir a base toda. --}}
            @if ($leads->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-800"
                     x-data="{ get vazio() { return escopo === 'marcados' && marcados.length === 0 } }">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        <template x-if="escopo === 'marcados'">
                            <span>
                                <span x-text="marcados.length"></span>
                                <span x-text="marcados.length === 1 ? 'lead selecionado' : 'leads selecionados'"></span>
                            </span>
                        </template>
                        <template x-if="escopo === 'filtro'">
                            <span>Os {{ number_format($noRecorte, 0, ',', '.') }} leads do filtro inteiro</span>
                        </template>

                        @if ($noRecorte > $leads->count())
                            <button type="button" class="ml-2 text-brand-500 underline-offset-2 hover:underline dark:text-brand-400"
                                    x-text="escopo === 'filtro' ? 'Voltar aos marcados' : 'Selecionar os {{ number_format($noRecorte, 0, ',', '.') }} do filtro'"
                                    x-on:click="escopo = escopo === 'filtro' ? 'marcados' : 'filtro'"></button>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label for="vendedor-lote" class="sr-only">Vendedor</label>
                        <select id="vendedor-lote" name="vendedor" class="campo w-auto py-2" x-model="vendedor">
                            <option value="">Escolha o vendedor</option>
                            @foreach ($vendedores as $vendedor)
                                <option value="{{ $vendedor->id }}">{{ $vendedor->nome }}</option>
                            @endforeach
                        </select>

                        <x-avalia.botao tamanho="sm" type="submit" name="acao" value="compartilhar"
                                        class="disabled:opacity-40" x-bind:disabled="vendedor === '' || vazio">
                            Compartilhar
                        </x-avalia.botao>

                        <x-avalia.botao variante="secundario" tamanho="sm" type="submit" name="acao" value="recolher"
                                        class="disabled:opacity-40" x-bind:disabled="vendedor === '' || vazio"
                                        x-on:click="if (! confirm('Tirar estes leads da lista do vendedor?')) $event.preventDefault()">
                            Recolher
                        </x-avalia.botao>

                        @if (! $removidos)
                            <x-avalia.botao variante="secundario" tamanho="sm" type="submit" name="acao" value="remover"
                                            class="disabled:opacity-40" x-bind:disabled="vazio"
                                            x-on:click="if (! confirm('Remover estes leads da base? Eles saem do trabalho, mas dá para restaurar.')) $event.preventDefault()">
                                Remover
                            </x-avalia.botao>
                        @endif
                    </div>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="tabela min-w-[72rem]">
                    <thead class="tabela-cabecalho">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left font-medium">
                                {{-- Marcar tudo age sobre a PÁGINA que está na tela. Para
                                     agir sobre o recorte inteiro existe o botão de cima. --}}
                                <input type="checkbox" class="caixa" title="Selecionar os desta página"
                                       x-on:change="escopo = 'marcados'; marcados = $event.target.checked
                                           ? [...$root.querySelectorAll('[name=\'leads[]\']')].map(c => (c.checked = true) && c.value)
                                           : ([...$root.querySelectorAll('[name=\'leads[]\']')].forEach(c => c.checked = false), [])">
                                <span class="sr-only">Selecionar os desta página</span>
                            </th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Lead</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">CNPJ</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Cidade</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Contato</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Compartilhado com</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($leads as $lead)
                            <tr>
                                <td class="px-5 py-4 text-left">
                                    <input type="checkbox" name="leads[]" class="caixa"
                                           value="{{ $lead->id }}" x-model="marcados" x-on:change="escopo = 'marcados'">
                                    <span class="sr-only">Selecionar {{ $lead->nome }}</span>
                                </td>
                                <td class="px-5 py-4 text-left">
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $lead->nome }}</span>
                                    @if ($lead->codigo || $lead->origem)
                                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ collect([$lead->codigo, $lead->origem])->filter()->implode(' · ') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-left tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    @if ($lead->cnpj)
                                    {{ $lead->cnpjRotulo() }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Sem CNPJ</span>
                                @endif
                                </td>
                                <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                    @if ($lead->cidadeRotulo())
                                    {{ $lead->cidadeRotulo() }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Não informada</span>
                                @endif
                                </td>
                                <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                    @if ($lead->telefone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->telefone) }}"
                                           class="hover:text-brand-500 dark:hover:text-brand-400 block whitespace-nowrap tabular-nums">
                                            {{ $lead->telefone }}
                                        </a>
                                    @endif
                                    @if ($lead->email)
                                        <a href="mailto:{{ $lead->email }}"
                                           class="hover:text-brand-500 dark:hover:text-brand-400 mt-0.5 block text-xs">
                                            {{ $lead->email }}
                                        </a>
                                    @endif
                                    @unless ($lead->temContato())
                                        <span class="text-gray-400 dark:text-gray-500">Sem contato</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-4 text-left">
                                    <span class="etiqueta {{ $lead->ativo ? 'etiqueta-sucesso' : 'etiqueta-neutra' }}">
                                        {{ $lead->ativo ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-left">
                                    @forelse ($lead->vendedores as $vendedor)
                                        <span class="etiqueta etiqueta-neutra mr-1"
                                              title="Desde {{ \Illuminate\Support\Carbon::parse($vendedor->pivot->compartilhado_em)->format('d/m/Y') }}">
                                            {{ $vendedor->nome }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-gray-400 dark:text-gray-500">Ninguém ainda</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    @if ($removidos)
                                        {{-- `form` aponta para o formulario declarado no fim da
                                             pagina: form dentro de form nao existe em HTML, e o
                                             botao pode viver longe do proprio formulario. --}}
                                        <x-avalia.botao variante="secundario" tamanho="sm"
                                                        form="restaurar-{{ $lead->id }}">
                                            Restaurar
                                        </x-avalia.botao>
                                    @else
                                        <x-avalia.botao variante="secundario" tamanho="sm" :href="route('leads.editar', $lead)">
                                            Editar
                                        </x-avalia.botao>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="tabela-vazia">
                                    Nenhum lead neste recorte. Ajuste o filtro ou cadastre um lead novo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-avalia.paginacao :pagina="$leads" />
        </div>
    </form>

    {{-- Os formulários de restaurar ficam FORA do formulário do lote: form dentro
         de form o navegador não aceita. O botão de cada linha se liga ao seu pelo
         atributo `form`. --}}
    @if ($removidos)
        @foreach ($leads as $lead)
            <form method="POST" action="{{ route('leads.restaurar', $lead->id) }}" id="restaurar-{{ $lead->id }}">
                @csrf
            </form>
        @endforeach
    @endif
@endsection
