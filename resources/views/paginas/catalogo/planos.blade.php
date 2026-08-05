@extends('layouts.app', ['title' => 'Planos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Planos</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Cada plano escolhe uma faixa do catálogo. A comissão é 10% do lucro do mês, 20% se houver excedente. Vale igual para todos.
            </p>
        </div>

        <x-avalia.botao :href="route('catalogo.planos.criar')">Novo plano</x-avalia.botao>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'planos'])

    @include('paginas.catalogo.avisos')

    <div class="overflow-hidden cartao">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[48rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Plano</th>
                                                <th class="px-5 py-3 text-right font-medium">Mensalidade</th>
                        <th class="px-5 py-3 text-right font-medium">Consumo mínimo</th>
                        <th class="px-5 py-3 text-right font-medium">Fatura minima</th>
                        <th class="px-5 py-3 text-right font-medium">Editar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($planos as $plano)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $plano->nome }}</span>
                                <span class="mt-0.5 flex flex-wrap gap-2 text-xs">
                                    @unless ($plano->ativo)
                                        <span class="etiqueta etiqueta-neutra">
                                            inativo
                                        </span>
                                    @endunless

                                    @unless ($faixaValida[$plano->id])
                                        {{-- Sem faixa valida nenhuma consulta acha preco. --}}
                                        <span class="etiqueta etiqueta-erro">
                                            faixa fora do catálogo
                                        </span>
                                    @endunless
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $plano->mensalidade }}</td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $plano->consumo_minimo }}</td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $plano->fatura_minima }}</td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="icone" title="Editar"
                                                :href="route('catalogo.planos.editar', $plano)">
                                    <x-avalia.icone nome="lapis" />
                                    <span class="sr-only">Editar</span>
                                </x-avalia.botao>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tabela-vazia">
                                Nenhum plano cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
