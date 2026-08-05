@extends('layouts.app', ['title' => 'Servicos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Servicos</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O que a Avalia vende. Servico inativo some das telas de venda mas continua
                explicando consulta e fatura antigas. Por isso nao ha exclusao, so desativacao.
            </p>
        </div>

        <x-avalia.botao :href="route('catalogo.servicos.criar')">Novo servico</x-avalia.botao>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'servicos'])

    @include('paginas.catalogo.avisos')

    <div class="overflow-hidden cartao">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Servico</th>
                        <th class="px-5 py-3 text-left font-medium">Categoria</th>
                        <th class="px-5 py-3 text-left font-medium">Situacao</th>
                        <th class="px-5 py-3"><span class="sr-only">Acoes</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($servicos as $servico)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $servico->nome }}</span>
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
                                        <span class="etiqueta etiqueta-alerta">aguarda liberacao</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="sm"
                                                :href="route('catalogo.servicos.editar', $servico)">Editar</x-avalia.botao>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tabela-vazia">
                                Nenhum servico cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
