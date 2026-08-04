@extends('layouts.app', ['title' => 'Planos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Planos</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Cada plano escolhe uma faixa do catalogo. A comissao e 10% sobre o consumo do mes, 20% se houver excedente. Vale igual para todos.
            </p>
        </div>

        <x-avalia.botao :href="route('catalogo.planos.criar')">Novo plano</x-avalia.botao>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'planos'])

    @include('paginas.catalogo.avisos')

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Plano</th>
                                                <th class="px-5 py-3 text-right font-medium">Mensalidade</th>
                        <th class="px-5 py-3 text-right font-medium">Consumo minimo</th>
                        <th class="px-5 py-3 text-right font-medium">Fatura minima</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($planos as $plano)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <a href="{{ route('catalogo.planos.editar', $plano) }}"
                                   class="hover:text-brand-500 dark:hover:text-brand-400 font-medium text-gray-800 dark:text-white/90">
                                    {{ $plano->nome }}
                                </a>
                                <span class="mt-0.5 flex flex-wrap gap-2 text-xs">
                                    @unless ($plano->ativo)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                            inativo
                                        </span>
                                    @endunless

                                    @unless ($faixaValida[$plano->id])
                                        {{-- Sem faixa valida nenhuma consulta acha preco. --}}
                                        <span class="bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400 rounded-full px-2 py-0.5 font-medium">
                                            faixa fora do catalogo
                                        </span>
                                    @endunless
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $plano->mensalidade }}</td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $plano->consumo_minimo }}</td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $plano->fatura_minima }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum plano cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
