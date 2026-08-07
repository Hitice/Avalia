@extends('layouts.app', ['title' => 'Faturas'])

@php
    use App\Support\Dinheiro;
    use App\Support\Rotulos;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Faturas</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $empresa->razao_social }}</p>
        </div>
        <x-avalia.ajuda assunto="Fatura">Falar com a Avalia</x-avalia.ajuda>
    </div>

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[40rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Período</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Mensalidade</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Consumo</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Vencimento</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Pagamento</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        {{-- Cada fatura carrega a propria composicao: a linha
                             abre e mostra de onde o total saiu, servico a
                             servico, sem chamado ao atendimento. --}}
                        <tr x-data="{ aberta: false }" class="align-top">
                            <td class="px-5 py-4 text-left">
                                <button type="button" @click="aberta = ! aberta"
                                        class="inline-flex items-center gap-1.5 font-medium text-gray-800 hover:text-brand-600 dark:text-white/90 dark:hover:text-brand-400">
                                    <svg class="size-3.5 transition-transform" :class="aberta && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    {{ $fatura->competenciaRotulo() }}
                                </button>

                                <div x-cloak x-show="aberta" class="mt-3 max-w-md rounded-xl bg-gray-50 p-4 text-sm dark:bg-gray-900/60">
                                    <div class="flex justify-between gap-6 py-1">
                                        <span class="text-gray-500 dark:text-gray-400">Mensalidade</span>
                                        <span class="tabular-nums">{{ Dinheiro::brl($fatura->mensalidade_cents) }}</span>
                                    </div>

                                    @foreach ($fatura->itens as $item)
                                        <div class="flex justify-between gap-6 py-1">
                                            <span class="text-gray-500 dark:text-gray-400">
                                                {{ $item->servico_nome }} · {{ $item->quantidade }} {{ $item->quantidade === 1 ? 'consulta' : 'consultas' }}@if ($item->quantidade_franquia > 0), {{ $item->quantidade_franquia }} na franquia @endif
                                            </span>
                                            <span class="tabular-nums">{{ Dinheiro::brl($item->valor_excedente_cents) }}</span>
                                        </div>
                                    @endforeach

                                    @if ($fatura->pagouSemUsarCents() > 0)
                                        <div class="flex justify-between gap-6 py-1">
                                            <span class="text-gray-500 dark:text-gray-400">Complemento até o consumo mínimo contratado</span>
                                            <span class="tabular-nums">{{ Dinheiro::brl($fatura->pagouSemUsarCents()) }}</span>
                                        </div>
                                    @endif

                                    <div class="mt-1 flex justify-between gap-6 border-t border-gray-200 pt-2 font-medium text-gray-800 dark:border-gray-700 dark:text-white/90">
                                        <span>Total</span>
                                        <span class="tabular-nums">{{ $fatura->totalRotulo() }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-left"><span class="etiqueta {{ Rotulos::faturaEtiqueta($fatura->situacao_pagamento) }}">{{ Rotulos::fatura($fatura->situacao_pagamento) }}</span></td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ Dinheiro::brl($fatura->mensalidade_cents) }}</td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ Dinheiro::brl($fatura->consumo_faturado_cents) }}</td>
                            <td class="px-5 py-4 text-right tabular-nums font-medium text-gray-800 dark:text-white/90">{{ $fatura->totalRotulo() }}</td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->vencimento()->format('d/m/Y') }}</td>
                            <td class="px-5 py-4 text-right">
                                @if (! $fatura->estaLiquidada() && $fatura->cobrancaAsaas?->invoice_url)
                                    <a class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                       href="{{ $fatura->cobrancaAsaas->invoice_url }}" target="_blank" rel="noopener noreferrer">Pagar fatura</a>
                                @elseif (! $fatura->estaLiquidada())
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Solicite a segunda via ao atendimento</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Paga</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tabela-vazia">As faturas serão exibidas aqui após o primeiro fechamento mensal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$faturas" />
    </div>
@endsection
