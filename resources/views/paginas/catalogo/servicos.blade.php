@extends('layouts.app', ['title' => 'Serviços'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Serviços</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Serviços do catálogo, agrupados por categoria.
            </p>
        </div>

        <x-avalia.botao :href="route('catalogo.servicos.criar')">Novo serviço</x-avalia.botao>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'servicos'])

    @include('paginas.catalogo.avisos')

    <div class="overflow-hidden cartao">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-4 py-3 text-right font-medium">Nº</th>
                        <th class="px-5 py-3 text-left font-medium">Serviço</th>
                        <th class="px-5 py-3 text-left font-medium">Categoria</th>
                        <th class="px-5 py-3 text-left font-medium">Situação</th>
                        <th class="px-5 py-3 text-right font-medium">Editar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($servicos as $servico)
                        <tr>
                            {{-- O numero de atendimento: "consulta 7" se dita ao telefone. --}}
                            <td class="px-4 py-4 text-right font-medium tabular-nums text-gray-500 dark:text-gray-400">{{ $servico->numero }}</td>
                            <td class="px-5 py-4 text-left">
                                <span class="flex items-center gap-1.5 font-medium text-gray-800 dark:text-white/90">
                                    {{ $servico->nome }}
                                    @if ($servico->suprimido())
                                        <x-avalia.cadeado titulo="Veicular: numeros suprimidos ate o contrato com o fornecedor" />
                                    @endif
                                </span>
                                <code class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $servico->codigo }}</code>
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $servico->rotuloCategoria() }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <div class="flex items-center gap-3">
                                    <x-avalia.interruptor
                                        :ligado="$servico->ativo"
                                        :acao="route('catalogo.servicos.alternar', $servico)"
                                        :titulo="$servico->ativo ? 'Ativo: clique para pausar' : 'Pausado: clique para ativar'" />

                                    @if ($servico->exige_liberacao)
                                        <span class="etiqueta etiqueta-alerta">Aguarda liberação</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="icone" title="Editar"
                                                :href="route('catalogo.servicos.editar', $servico)">
                                    <x-avalia.icone nome="lapis" />
                                    <span class="sr-only">Editar</span>
                                </x-avalia.botao>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tabela-vazia">
                                Nenhum serviço cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
