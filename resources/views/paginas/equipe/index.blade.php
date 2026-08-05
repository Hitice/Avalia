@extends('layouts.app', ['title' => 'Equipe'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Equipe</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Quem trabalha na Avalia e a comissão de cada vendedor.
            </p>
        </div>

        <x-avalia.botao :href="route('equipe.criar')">Nova pessoa</x-avalia.botao>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[46rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Pessoa</th>
                        <th class="px-5 py-3 text-left font-medium">Função</th>
                        <th class="px-5 py-3 text-right font-medium">Comissão</th>
                        <th class="px-5 py-3 text-right font-medium">Empresas</th>
                        <th class="px-5 py-3 text-left font-medium">Situação</th>
                        <th class="px-5 py-3 text-right font-medium">Editar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($membros as $membro)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $membro->nome }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $membro->email }}</span>
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $membro->ehAdmin() ? 'Administração' : 'Vendedor' }}
                                @if ($membro->ehSuper())
                                    <span class="etiqueta etiqueta-alerta ml-1">super</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $membro->ehAdmin() ? '-' : $membro->comissao_pct.'%' }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $membro->ehAdmin() ? '-' : $membro->clientes_count }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ $membro->ativo ? 'etiqueta-sucesso' : 'etiqueta-neutra' }}">
                                    {{ $membro->ativo ? 'ativo' : 'inativo' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="icone" title="Editar"
                                                :href="route('equipe.editar', $membro)">
                                    <x-avalia.icone nome="lapis" />
                                    <span class="sr-only">Editar</span>
                                </x-avalia.botao>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
