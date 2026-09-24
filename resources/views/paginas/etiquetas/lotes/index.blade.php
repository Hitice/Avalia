@extends('layouts.ferramenta', ['title' => 'Tiragens'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Tiragens</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Cada tiragem abre um bloco de plaquinhas em branco, prontas para imprimir e vender.
            </p>
        </div>

        <x-avalia.botao :href="route('etiquetas.lotes.criar')">Nova tiragem</x-avalia.botao>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="cartao overflow-hidden">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="tabela-th text-left">Tiragem</th>
                        <th scope="col" class="tabela-th text-right">Plaquinhas</th>
                        <th scope="col" class="tabela-th text-left">Aberta em</th>
                        <th scope="col" class="tabela-th text-right"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($lotes as $lote)
                        <tr>
                            <td class="tabela-td">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $lote->codigo }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $lote->titulo }}</span>
                            </td>
                            <td class="tabela-td text-right tabular-nums">{{ $lote->etiquetas_count }}</td>
                            <td class="tabela-td">{{ $lote->created_at->format('d/m/Y') }}</td>
                            <td class="tabela-td text-right whitespace-nowrap">
                                <x-avalia.botao variante="secundario" tamanho="sm" :href="route('etiquetas.lotes.ficha', $lote)">
                                    Abrir
                                </x-avalia.botao>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tabela-vazia">
                                Abra uma tiragem para gerar os códigos, baixar os arquivos e mandar imprimir.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$lotes" />
    </div>
@endsection
