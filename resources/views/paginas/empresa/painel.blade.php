@extends('layouts.app', ['title' => 'Área do cliente'])

@php
    use App\Support\Dinheiro;
    $pagamento = ['liquidado' => 'etiqueta-sucesso', 'vencido' => 'etiqueta-alerta', 'pendente' => 'etiqueta-neutra'];
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $empresa->razao_social }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Área do cliente</p>
    </div>

    {{-- Conta suspensa entra, mas nao consulta. A tela diz o porque em vez de
         so esconder o botao. Cliente sem explicacao liga para o vendedor. --}}
    @if (! $empresa->podeConsultar())
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
            <svg class="mt-0.5 size-5 shrink-0 fill-current" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $empresa->motivoSuspensao() }}</span>
        </div>
    @endif

    @if ($plano)
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="cartao p-5">
                <span class="rotulo-grupo block">Plano contratado</span>
                <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ $plano->nome }}</span>
            </div>
            <div class="cartao p-5">
                <span class="rotulo-grupo block">Mensalidade</span>
                <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ Dinheiro::brl($plano->mensalidade_cents) }}</span>
            </div>
            <div class="cartao p-5">
                <span class="rotulo-grupo block">Competência atual</span>
                <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ $competencia }}</span>
            </div>
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Franquia de consultas</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[36rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th class="px-5 py-3 text-left font-medium">Serviço</th>
                        <th class="px-5 py-3 text-right font-medium">Incluídas</th>
                        <th class="px-5 py-3 text-right font-medium">Utilizadas</th>
                        <th class="px-5 py-3 text-right font-medium">Disponíveis</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($plano->franquias as $franquia)
                            @php $utilizadas = (int) ($uso[$franquia->servico_id] ?? 0); @endphp
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $franquia->servico->nome }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $franquia->quantidade }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $utilizadas }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ max(0, $franquia->quantidade - $utilizadas) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabela-vazia">Não há franquias configuradas para este plano.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="cartao mt-6 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Faturas</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="tabela min-w-[36rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th class="px-5 py-3 text-left font-medium">Período</th>
                    <th class="px-5 py-3 text-left font-medium">Situação</th>
                    <th class="px-5 py-3 text-right font-medium">Total</th>
                    <th class="px-5 py-3 text-right font-medium">Vencimento</th>
                    <th class="px-5 py-3 text-right font-medium">Pagamento</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        <tr>
                            <td class="px-5 py-4 text-left">{{ $fatura->competenciaRotulo() }}</td>
                            <td class="px-5 py-4 text-left"><span class="etiqueta {{ $pagamento[$fatura->situacao_pagamento] ?? 'etiqueta-neutra' }}">{{ $fatura->situacao_pagamento }}</span></td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->totalRotulo() }}</td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->vencimento()->format('d/m/Y') }}</td>
                            <td class="px-5 py-4 text-right">
                                @if (! $fatura->estaLiquidada() && $fatura->cobrancaAsaas?->invoice_url)
                                    <a class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" href="{{ $fatura->cobrancaAsaas->invoice_url }}" target="_blank" rel="noopener noreferrer">Pagar fatura</a>
                                @elseif (! $fatura->estaLiquidada() && $fatura->cobrancaAsaas?->situacao === 'aguardando_configuracao')
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Em preparação</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                            <tr><td colspan="5" class="tabela-vazia">Nenhuma fatura disponível.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 text-sm dark:border-gray-800">
            <a class="text-brand-600 hover:text-brand-700 dark:text-brand-400" href="https://wa.me/5534991176599" target="_blank" rel="noopener noreferrer">Precisa de ajuda? Fale com o atendimento.</a>
        </div>
    </div>

    @if ($documentos->isNotEmpty())
        <div class="cartao mt-6 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Documentos e aceites</h2>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($documentos as $documento)
                    <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white/90">{{ $documento->titulo }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Versão {{ $documento->versao }}</p>
                            <details class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                <summary class="cursor-pointer text-brand-600 dark:text-brand-400">Ler documento</summary>
                                <div class="mt-2 whitespace-pre-line">{{ $documento->conteudo }}</div>
                            </details>
                        </div>
                        @if (in_array($documento->id, $aceites, true))
                            <span class="etiqueta etiqueta-sucesso">Aceito</span>
                        @elseif ($documento->exige_aceite)
                            <form method="POST" action="{{ route('empresa.documentos.aceitar', $documento) }}">
                                @csrf
                                <x-avalia.botao variante="secundario" tamanho="sm">Li e aceito</x-avalia.botao>
                            </form>
                        @else
                            <span class="etiqueta etiqueta-neutra">Disponível</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
