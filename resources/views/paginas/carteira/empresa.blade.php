@extends('layouts.app', ['title' => $empresa->razao_social])

@php
    use App\Support\Dinheiro;
    use App\Support\Rotulos;
@endphp

@section('content')
    <a href="{{ route('carteira') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Minha carteira
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $empresa->razao_social }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $empresa->cnpjRotulo() }}
                <span class="etiqueta {{ Rotulos::empresaEtiqueta($empresa->situacao) }} ml-2">{{ Rotulos::empresa($empresa->situacao) }}</span>
            </p>
        </div>
        <div class="flex gap-2">
            <x-avalia.botao variante="secundario" tamanho="sm" :href="route('empresas.editar', $empresa)">Editar cadastro</x-avalia.botao>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Plano</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ $empresa->plano?->nome ?? 'Sem plano' }}</span>
            @if ($empresa->plano)
                <span class="ajuda-campo">Mensalidade {{ $empresa->plano->mensalidade }} · mínimo {{ $empresa->plano->consumo_minimo }}</span>
            @endif
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consumo em {{ $competencia }}</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($consumo) }}</span>
            <span class="ajuda-campo">{{ $quantidade }} {{ $quantidade === 1 ? 'consulta' : 'consultas' }} no período.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Contato</span>
            <span class="mt-1 block text-sm text-gray-800 dark:text-white/90">{{ $empresa->responsavel_nome ?: 'Sem responsável definido' }}</span>
            <span class="ajuda-campo">{{ $empresa->telefone ?: $empresa->email }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Cidade</span>
            <span class="mt-1 block text-sm text-gray-800 dark:text-white/90">{{ $empresa->cidade ? $empresa->cidade.' / '.$empresa->uf : 'Sem endereço cadastrado' }}</span>
        </div>
    </div>

    {{-- Faturas pelo preco de venda: o que o cliente paga e o que fala na
         conversa. Custo, lucro e margem sao da ficha da administracao. --}}
    <div class="cartao overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Faturas</h2>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela min-w-[32rem]">
                <thead class="tabela-cabecalho tabela-cabecalho-fixo"><tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Período</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Vencimento</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        <tr>
                            <td class="px-5 py-4 text-left">{{ $fatura->competenciaRotulo() }}</td>
                            <td class="px-5 py-4 text-left"><span class="etiqueta {{ Rotulos::faturaEtiqueta($fatura->situacao_pagamento) }}">{{ Rotulos::fatura($fatura->situacao_pagamento) }}</span></td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->totalRotulo() }}</td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->vencimento()->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="tabela-vazia">Nenhuma fatura fechada ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
