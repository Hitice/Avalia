@extends('layouts.app', ['title' => 'Minha carteira'])

@php
    use App\Support\Dinheiro;
    use App\Support\Rotulos;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Minha carteira</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Suas empresas e a sua comissão. A comissão é liberada quando a empresa paga a fatura.
            </p>
        </div>

        <x-avalia.botao :href="route('empresas.criar')">Nova empresa</x-avalia.botao>
    </div>

    @include('paginas.carteira.abas')

    @include('paginas.catalogo.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Comissão liberada</span>
            <span class="mt-1 block text-xl font-semibold text-success-600 dark:text-success-500">
                {{ Dinheiro::brl($aReceber) }}
            </span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Aguardando pagamento</span>
            <span class="mt-1 block text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ Dinheiro::brl($aConfirmar) }}
            </span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Empresas</span>
            <span class="mt-1 block text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ $empresas->count() }}
            </span>
        </div>
    </div>

    <div class="cartao overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Empresas</h2>
            <p class="ajuda-campo mt-1">Consumo de {{ $competencia }}, que ainda pode mudar até o fechamento.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th class="px-5 py-3 text-left font-medium">Plano</th>
                        <th class="px-5 py-3 text-left font-medium">Situação</th>
                        <th class="px-5 py-3 text-right font-medium">Consumo do mês</th>
                        <th class="px-5 py-3 text-right font-medium">Editar</th>
                        <th class="px-5 py-3 text-right font-medium">Remover</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($empresas as $empresa)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <a class="font-medium text-gray-800 hover:text-brand-600 dark:text-white/90 dark:hover:text-brand-400"
                                   href="{{ route('carteira.empresa', $empresa) }}">{{ $empresa->razao_social }}</a>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $empresa->cnpjRotulo() }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $empresa->plano?->nome ?? 'Sem plano' }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ Rotulos::empresaEtiqueta($empresa->situacao) }}">
                                    {{ Rotulos::empresa($empresa->situacao) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl((int) ($consumo[$empresa->id] ?? 0)) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="icone" title="Editar"
                                                :href="route('empresas.editar', $empresa)">
                                    <x-avalia.icone nome="lapis" />
                                    <span class="sr-only">Editar</span>
                                </x-avalia.botao>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('empresas.remover', $empresa) }}"
                                      x-data="{ armado: false }"
                                      @submit="if (! armado) { $event.preventDefault(); armado = true; setTimeout(() => armado = false, 3500) }">
                                    @csrf
                                    @method('DELETE')
                                    <x-avalia.botao variante="secundario" tamanho="icone" title="Remover"
                                                    x-show="! armado">
                                        <x-avalia.icone nome="lixeira" />
                                        <span class="sr-only">Remover</span>
                                    </x-avalia.botao>
                                    {{-- Segundo clique confirma; 3,5s sem ele desarma. --}}
                                    <button type="submit" x-cloak x-show="armado"
                                            class="botao botao-sm bg-error-500 text-white hover:bg-error-600">
                                        Remover?
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tabela-vazia">Nenhuma empresa na sua carteira.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="cartao mt-6 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Comissão por competência</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th class="px-5 py-3 text-left font-medium">Competência</th>
                        <th class="px-5 py-3 text-left font-medium">Pagamento</th>
                        <th class="px-5 py-3 text-right font-medium">Sua comissão</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        <tr>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">
                                {{ $fatura->cliente->razao_social }}
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $fatura->competenciaRotulo() }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ Rotulos::faturaEtiqueta($fatura->situacao_pagamento) }}">
                                    {{ Rotulos::fatura($fatura->situacao_pagamento) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right font-medium tabular-nums whitespace-nowrap
                                       {{ $fatura->comissao_liberada_em ? 'text-success-600 dark:text-success-500' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ Dinheiro::brl($fatura->comissao_cents) }}
                                @if ($fatura->comissao_liberada_em)
                                    <span class="mt-0.5 block text-xs font-normal">Liberada</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tabela-vazia">Nenhuma competência fechada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
