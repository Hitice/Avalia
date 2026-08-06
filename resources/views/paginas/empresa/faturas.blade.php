@extends('layouts.app', ['title' => 'Faturas'])

@php
    use App\Support\Dinheiro;
    $pagamento =['liquidado' => 'etiqueta-sucesso', 'vencido' => 'etiqueta-alerta', 'pendente' => 'etiqueta-neutra'];
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
                        <tr>
                            <td class="px-5 py-4 text-left">{{ $fatura->competenciaRotulo() }}</td>
                            <td class="px-5 py-4 text-left"><span class="etiqueta {{ $pagamento[$fatura->situacao_pagamento] ?? 'etiqueta-neutra' }}">{{ $fatura->situacao_pagamento }}</span></td>
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
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Liquidada</span>
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
