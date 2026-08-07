@extends('layouts.app', ['title' => 'Empresas'])

@php
    use App\Support\Dinheiro;
    use App\Support\Rotulos;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Empresas</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Empresas contratantes e a situação de cada uma.
            </p>
        </div>

        <x-avalia.botao :href="route('empresas.criar')">Nova empresa</x-avalia.botao>
    </div>

    @if ($quantidadeRemovidas > 0 || $removidas)
        <div class="mb-6">
            <x-avalia.segmentado
                :atual="$removidas ? 'removidas' : 'ativas'"
                :itens="[
                    'ativas' => ['rotulo' => 'Em uso', 'url' => route('empresas.index')],
                    'removidas' => ['rotulo' => 'Removidas ('.$quantidadeRemovidas.')', 'url' => route('empresas.index', ['removidas' => 1])],
                ]" />
        </div>
    @endif

    @include('paginas.catalogo.avisos')

    <div class="cartao overflow-hidden">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[52rem]">
                <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th class="px-5 py-3 text-left font-medium">Plano</th>
                        <th class="px-5 py-3 text-left font-medium">Vendedor</th>
                        <th class="px-5 py-3 text-left font-medium">Situação</th>
                        <th class="px-5 py-3 text-right font-medium">{{ $removidas ? 'Restaurar' : 'Abrir' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($empresas as $empresa)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $empresa->razao_social }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $empresa->cnpjRotulo() ?: 'sem CNPJ' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                @if ($empresa->plano)
                                    {{ $empresa->plano->nome }}
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        mínimo {{ Dinheiro::faixa($empresa->plano->consumo_minimo_cents) }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-600">Sem plano</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $empresa->vendedor?->nome ?? '-' }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ Rotulos::empresaEtiqueta($empresa->situacao) }}">
                                    {{ Rotulos::empresa($empresa->situacao) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($removidas)
                                    <form method="POST" action="{{ route('empresas.restaurar', $empresa->id) }}">
                                        @csrf
                                        <x-avalia.botao variante="secundario" tamanho="sm">Restaurar</x-avalia.botao>
                                    </form>
                                @else
                                    <x-avalia.botao variante="secundario" tamanho="sm"
                                                    :href="route('empresas.ficha', $empresa)">Ficha</x-avalia.botao>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tabela-vazia">Nenhuma empresa cadastrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
