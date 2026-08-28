@extends('layouts.app', ['title' => 'Leads'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Leads</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A base de prospecção e a distribuição dela.
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
        {{-- Os recortes que não se digitam entram por aqui: o próprio número é o
             filtro, e o link preserva a busca e a cidade já escolhidas. --}}
        <x-avalia.cartao-indicador rotulo="Com telefone" :valor="number_format($comTelefone, 0, ',', '.')"
                                   :href="route('leads.index', array_merge(request()->query(), ['contato' => 'telefone']))" />
        <x-avalia.cartao-indicador rotulo="Com e-mail" :valor="number_format($comEmail, 0, ',', '.')"
                                   :href="route('leads.index', array_merge(request()->query(), ['contato' => 'email']))" />
        <x-avalia.cartao-indicador rotulo="Sem vendedor" :valor="number_format($semVendedor, 0, ',', '.')"
                                   :href="route('leads.index', array_merge(request()->query(), ['vendedor' => 'sem']))" />
    </div>

    <x-avalia.filtro-leads :acao="route('leads.index')" :vendedores="$vendedores"
                           :ufs="$ufs" :cidades="$cidades" :escolha="$escolha" />

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
                                            x-on:click="if (! confirm('Remover estes leads da base? Dá para restaurar depois.')) $event.preventDefault()">
                                Remover
                            </x-avalia.botao>
                        @endif
                    </div>
                </div>
            @endif

            <div class="tabela-rolagem">
                <table class="tabela min-w-[72rem]">
                    <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left font-medium">
                                {{-- Marcar tudo age sobre a lista que está na tela. --}}
                                <input type="checkbox" class="caixa" title="Selecionar os desta lista"
                                       x-on:change="escopo = 'marcados'; marcados = $event.target.checked
                                           ? [...$root.querySelectorAll('[name=\'leads[]\']')].map(c => (c.checked = true) && c.value)
                                           : ([...$root.querySelectorAll('[name=\'leads[]\']')].forEach(c => c.checked = false), [])">
                                <span class="sr-only">Selecionar os desta lista</span>
                            </th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Lead</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">CNPJ</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Cidade</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Contato</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Compartilhado com</th>
                            <th scope="col" class="px-5 py-3 text-center font-medium">Em circulação</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Editar</th>
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
                                    <span class="etiqueta {{ $lead->situacao->etiqueta() }}">
                                        {{ $lead->situacao->rotulo() }}
                                    </span>
                                    @if ($lead->agendado_para)
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ $lead->agendado_para->format('d/m/Y H:i') }}
                                        </span>
                                    @endif
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
                                {{-- Um clique tira o lead da distribuição ou devolve. Lead que
                                     já é cliente não tem chave: quem desfaz conversão é o
                                     cadastro da empresa, do outro lado. --}}
                                <td class="px-5 py-4 text-center">
                                    @if ($removidos || $lead->jaEhCliente())
                                        <span class="text-xs text-gray-400 dark:text-gray-500">&mdash;</span>
                                    @else
                                        <x-avalia.interruptor
                                            :ligado="$lead->situacao !== App\Enums\SituacaoLead::Bloqueado"
                                            :form="'alternar-'.$lead->id"
                                            ligado-rotulo="Em circulação" desligado-rotulo="Bloqueado"
                                            :titulo="$lead->situacao === App\Enums\SituacaoLead::Bloqueado
                                                ? 'Devolver à prospecção'
                                                : 'Tirar da prospecção'" />
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    {{-- `form` aponta para o formulário declarado no fim da página:
                                         form dentro de form o navegador descarta, e os botões de
                                         dentro passam a enviar o de fora. --}}
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($removidos)
                                            <button type="submit" class="acao-linha" form="restaurar-{{ $lead->id }}"
                                                    title="Devolver o lead à base">
                                                <x-avalia.icone nome="desfazer" />
                                                <span class="sr-only">Restaurar</span>
                                            </button>
                                        @else
                                            <a class="acao-linha" href="{{ route('leads.editar', $lead) }}"
                                               title="Editar o cadastro do lead">
                                                <x-avalia.icone nome="lapis" />
                                                <span class="sr-only">Editar</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="tabela-vazia">
                                    Nenhum lead neste recorte. Ajuste o filtro ou cadastre um lead novo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Teto que não se anuncia lê como se a tela estivesse mostrando
                 tudo, e aí o operador conclui que o filtro não achou o resto. --}}
            <div class="border-t border-gray-100 px-6 py-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                @if ($noRecorte > $leads->count())
                    Mostrando {{ number_format($leads->count(), 0, ',', '.') }}
                    de {{ number_format($noRecorte, 0, ',', '.') }} leads. Refine o filtro para ver o resto;
                    as ações em lote sobre o filtro alcançam os {{ number_format($noRecorte, 0, ',', '.') }}.
                @else
                    {{ number_format($leads->count(), 0, ',', '.') }}
                    {{ $leads->count() === 1 ? 'lead' : 'leads' }} nesta lista
                @endif
            </div>
        </div>
    </form>

    {{-- Os formulários de linha ficam FORA do formulário do lote: form dentro de
         form o navegador descarta, e os botões de dentro passariam a enviar o de
         fora. Cada botão se liga ao seu pelo atributo `form`. --}}
    @foreach ($leads as $lead)
        @if ($removidos)
            <form method="POST" action="{{ route('leads.restaurar', $lead->id) }}" id="restaurar-{{ $lead->id }}">
                @csrf
            </form>
        @elseif (! $lead->jaEhCliente())
            <form method="POST" action="{{ route('leads.alternar', $lead) }}" id="alternar-{{ $lead->id }}">
                @csrf
                @method('PATCH')
            </form>
        @endif
    @endforeach
@endsection
