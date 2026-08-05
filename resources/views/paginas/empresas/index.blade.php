@extends('layouts.app', ['title' => 'Empresas'])

@php
    use App\Support\Dinheiro;

    $etiqueta = [
        'ativo' => 'etiqueta-sucesso',
        'inadimplente' => 'etiqueta-alerta',
        'bloqueado' => 'etiqueta-erro',
        'inativo' => 'etiqueta-neutra',
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Empresas</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Quem contrata a Avalia. A situação comanda o acesso e a cobrança: só empresa ativa consulta.
            </p>
        </div>

        <x-avalia.botao :href="route('empresas.criar')">Nova empresa</x-avalia.botao>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[52rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th class="px-5 py-3 text-left font-medium">Plano</th>
                        <th class="px-5 py-3 text-left font-medium">Vendedor</th>
                        <th class="px-5 py-3 text-left font-medium">Situação</th>
                        <th class="px-5 py-3 text-right font-medium">Abrir</th>
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
                                    <span class="text-gray-400 dark:text-gray-600">sem plano</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $empresa->vendedor?->nome ?? '-' }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ $etiqueta[$empresa->situacao] ?? 'etiqueta-neutra' }}">
                                    {{ $empresa->situacao }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="sm"
                                                :href="route('empresas.ficha', $empresa)">Ficha</x-avalia.botao>
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
