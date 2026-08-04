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

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
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
                                        <span class="bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400 rounded-full px-2 py-0.5 font-medium">
                                            disponivel
                                        </span>
                                    @endif

                                    @unless ($servico->ativo)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                            inativo
                                        </span>
                                    @endunless

                                    @if ($servico->exige_liberacao)
                                        <span class="bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400 rounded-full px-2 py-0.5 font-medium">
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
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum servico cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
