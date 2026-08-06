@extends('layouts.app', ['title' => $empresa->razao_social])

@php
    use App\Support\Dinheiro;
    use App\Support\Rotulos;

    $plano = $empresa->plano;
    $faturado = $plano ? max($consumo, $plano->consumo_minimo_cents) : $consumo;
@endphp

@section('content')
    <a href="{{ route('empresas.index') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Empresa
    </a>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $empresa->razao_social }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $empresa->cnpjRotulo() ?: 'sem CNPJ' }}
                @if ($plano) · {{ $plano->nome }} @endif
                @if ($empresa->vendedor) · carteira de {{ $empresa->vendedor->nome }} @endif
            </p>
        </div>

        <x-avalia.botao variante="secundario" :href="route('empresas.editar', $empresa)">Editar cadastro</x-avalia.botao>
    </div>

    @include('paginas.catalogo.avisos')

    @if ($motivo = $empresa->motivoSuspensao())
        <div class="aviso aviso-alerta mb-6">{{ $motivo }}</div>
    @endif

    @unless ($plano)
        <div class="aviso aviso-alerta mb-6">
            Sem plano contratado não há serviço liberado para consulta.
        </div>
    @endunless

    <div class="cartao mb-6 p-6">
        <h2 class="mb-4 font-medium text-gray-800 dark:text-white/90">Condições comerciais</h2>
        <div class="grid gap-4 text-sm sm:grid-cols-3">
            <div><span class="rotulo-grupo block">Vigência</span><span class="mt-1 block text-gray-800 dark:text-white/90">{{ match($empresa->vigencia_tipo) { 'sem_vigencia' => 'Sem vigência', '12_meses' => '12 meses', '24_meses' => '24 meses', 'carencia' => 'Carência especial', default => 'Não definida' } }}</span></div>
            <div><span class="rotulo-grupo block">Período do contrato</span><span class="mt-1 block text-gray-800 dark:text-white/90">{{ $empresa->contrato_inicio?->format('d/m/Y') ?? 'Não informado' }} até {{ $empresa->contrato_fim?->format('d/m/Y') ?? 'Não informado' }}</span></div>
            <div><span class="rotulo-grupo block">Taxa de adesão</span><span class="mt-1 block text-gray-800 dark:text-white/90">{{ $empresa->adesao ? Dinheiro::brl($empresa->adesao->valor_cents).' em '.$empresa->adesao->parcelas.'x' : 'Não cadastrada' }}</span></div>
        </div>
    </div>

    <div class="grid gap-6">
        <div class="cartao p-6">
            <div class="mb-5 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Competência {{ $competencia }}</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $quantidade }} consulta(s)</span>
            </div>

            <table class="tabela">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr>
                        <td class="py-3 text-left text-gray-600 dark:text-gray-300">Consumo realizado</td>
                        <td class="py-3 text-right tabular-nums text-gray-800 dark:text-white/90">
                            {{ Dinheiro::brl($consumo) }}
                        </td>
                    </tr>
                    @if ($plano)
                        <tr>
                            <td class="py-3 text-left text-gray-600 dark:text-gray-300">
                                Consumo mínimo
                                @if ($faturado > $consumo)
                                    <span class="etiqueta etiqueta-neutra ml-1">piso vale</span>
                                @endif
                            </td>
                            <td class="py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::faixa($plano->consumo_minimo_cents) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 text-left text-gray-600 dark:text-gray-300">Mensalidade</td>
                            <td class="py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($plano->mensalidade_cents) }}
                            </td>
                        </tr>
                        <tr class="border-t-2 border-gray-200 dark:border-gray-700">
                            <td class="py-4 text-left font-medium text-gray-800 dark:text-white/90">Fatura se fechar hoje</td>
                            <td class="py-4 text-right text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($plano->mensalidade_cents + $faturado) }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            @if ($plano)
                <form method="POST" action="{{ route('empresas.fechar', $empresa) }}" class="mt-6"
                      onsubmit="return confirm('Fechar a competência {{ $competencia }}? Depois disso ela não aceita consulta nova.')">
                    @csrf
                    <x-avalia.botao>Fechar competência</x-avalia.botao>
                </form>
            @endif
        </div>
    </div>

    <div class="cartao mt-6 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Faturas fechadas</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="tabela min-w-[48rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Competência</th>
                        <th class="px-5 py-3 text-left font-medium">Pagamento</th>
                        <th class="px-5 py-3 text-right font-medium">Total</th>
                        <th class="px-5 py-3 text-right font-medium">Imposto</th>
                        <th class="px-5 py-3 text-right font-medium">Custo</th>
                        <th class="px-5 py-3 text-right font-medium">Comissão</th>
                        <th class="px-5 py-3 text-right font-medium">Lucro</th>
                        <th class="px-5 py-3 text-right font-medium">Vence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        <tr>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">
                                {{ $fatura->competenciaRotulo() }}
                                @if ($fatura->pagouSemUsarCents() > 0)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        pagou {{ Dinheiro::brl($fatura->pagouSemUsarCents()) }} sem usar
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left">
                                @php
                                @endphp
                                <span class="etiqueta {{ Rotulos::faturaEtiqueta($fatura->situacao_pagamento) }}">
                                    {{ Rotulos::fatura($fatura->situacao_pagamento) }}
                                </span>
                                @if ($fatura->comissao_liberada_em)
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">comissão liberada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-medium tabular-nums text-gray-800 dark:text-white/90">
                                {{ $fatura->totalRotulo() }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->imposto_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->custo_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->comissao_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right font-medium tabular-nums text-success-600 dark:text-success-500">
                                {{ Dinheiro::brl($fatura->lucro_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $fatura->vencimento()->format('d/m/Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="tabela-vazia">Nenhuma competência fechada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
