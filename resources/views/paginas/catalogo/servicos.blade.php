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
                        <th class="px-5 py-3 text-right font-medium">Precos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($servicos as $servico)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <a href="{{ route('catalogo.servicos.editar', $servico) }}"
                                   class="hover:text-brand-500 dark:hover:text-brand-400 font-medium text-gray-800 dark:text-white/90">
                                    {{ $servico->nome }}
                                </a>
                                <code class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $servico->codigo }}</code>
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $servico->rotuloCategoria() }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="flex flex-wrap gap-2 text-xs">
                                    @if ($servico->disponivel())
                                        <span class="etiqueta etiqueta-sucesso">
                                            disponivel
                                        </span>
                                    @endif

                                    @unless ($servico->ativo)
                                        <span class="etiqueta etiqueta-neutra">
                                            inativo
                                        </span>
                                    @endunless

                                    @if ($servico->exige_liberacao)
                                        <span class="etiqueta etiqueta-alerta">
                                            aguarda liberacao
                                        </span>
                                    @endif
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $servico->precos_count }}
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
