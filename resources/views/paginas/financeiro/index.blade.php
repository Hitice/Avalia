@extends('layouts.app', ['title' => 'Financeiro'])

@php
    use App\Models\Fatura;
    use App\Support\Dinheiro;

    $etiqueta = [
        'liquidado' => 'etiqueta-sucesso',
        'vencido' => 'etiqueta-alerta',
        'pendente' => 'etiqueta-neutra',
        'cancelado' => 'etiqueta-erro',
        'estornado' => 'etiqueta-erro',
    ];

    $filtros = collect(['' => 'Todas'] + array_combine(Fatura::SITUACOES_PAGAMENTO, array_map('ucfirst', Fatura::SITUACOES_PAGAMENTO)))
        ->map(fn ($rotulo, $chave) => [
            'rotulo' => $rotulo,
            'url' => route('financeiro.index', array_filter(['situacao' => $chave])),
        ])
        ->all();
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Financeiro</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Acompanhe as faturas de todas as empresas e confirme os pagamentos recebidos.
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">A receber</span>
            <span class="mt-1 block text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ Dinheiro::brl($totais['a_receber']) }}
            </span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Vencido</span>
            <span class="mt-1 block text-xl font-semibold {{ $totais['vencido'] > 0 ? 'text-error-600 dark:text-error-400' : 'text-gray-800 dark:text-white/90' }}">
                {{ Dinheiro::brl($totais['vencido']) }}
            </span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Liquidado</span>
            <span class="mt-1 block text-xl font-semibold text-success-600 dark:text-success-500">
                {{ Dinheiro::brl($totais['liquidado']) }}
            </span>
        </div>
    </div>

    <div class="mb-6">
        <x-avalia.segmentado rotulo="Faturas" :atual="$situacao ?? ''" :itens="$filtros" />
    </div>

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[60rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Competência</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Pagamento</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Lucro</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Comissão</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Vence</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Confirmar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <a href="{{ route('empresas.ficha', $fatura->cliente) }}"
                                   class="hover:text-brand-500 dark:hover:text-brand-400 font-medium text-gray-800 dark:text-white/90">
                                    {{ $fatura->cliente->razao_social }}
                                </a>
                                @if ($fatura->vendedor)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $fatura->vendedor->nome }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $fatura->competenciaRotulo() }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ $etiqueta[$fatura->situacao_pagamento] ?? 'etiqueta-neutra' }}">
                                    {{ $fatura->situacao_pagamento }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right font-medium tabular-nums whitespace-nowrap text-gray-800 dark:text-white/90">
                                {{ $fatura->totalRotulo() }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->lucro_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->comissao_cents) }}
                                @if ($fatura->comissao_liberada_em)
                                    <span class="mt-0.5 block text-xs text-success-600 dark:text-success-500">liberada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $fatura->vencimento()->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($fatura->estaLiquidada())
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $fatura->liquidada_em?->format('d/m/Y') }}
                                    </span>
                                @else
                                    {{-- A justificativa e obrigatoria porque esta e a unica porta
                                         pela qual dinheiro e dado como recebido sem ter entrado, e
                                         ela libera a comissao do vendedor na mesma hora. --}}
                                    <div x-data="{ aberto: false }" class="inline-block text-left">
                                        <x-avalia.botao variante="secundario" tamanho="icone"
                                                        title="Confirmar pagamento recebido"
                                                        x-show="! aberto" x-on:click="aberto = true">
                                            <x-avalia.icone nome="confirmar" />
                                            <span class="sr-only">Confirmar pagamento recebido</span>
                                        </x-avalia.botao>

                                        <form method="POST" action="{{ route('financeiro.liquidar', $fatura) }}"
                                              x-show="aberto" x-cloak class="flex items-center gap-2">
                                            @csrf

                                            <label for="motivo-{{ $fatura->id }}" class="sr-only">
                                                Como o pagamento foi conferido
                                            </label>
                                            <input id="motivo-{{ $fatura->id }}" name="motivo" type="text"
                                                   class="campo-linha w-64" required minlength="10" maxlength="255"
                                                   placeholder="Como o pagamento foi conferido">

                                            <x-avalia.botao tamanho="sm">Confirmar</x-avalia.botao>
                                            <x-avalia.botao variante="secundario" tamanho="sm"
                                                            type="button" x-on:click="aberto = false">
                                                Cancelar
                                            </x-avalia.botao>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="tabela-vazia">Nenhuma fatura corresponde a esta situação. Selecione outro filtro para continuar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($comissoes->isNotEmpty())
        <div class="cartao mt-6 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Comissão a repassar</h2>
                <p class="ajuda-campo mt-1">Valores já devidos, apurados sobre faturas com pagamento confirmado.</p>
            </div>

            <div class="overflow-x-auto">
            <table class="tabela min-w-[28rem]">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($comissoes as $linha)
                        <tr>
                            <td class="px-6 py-4 text-left text-gray-800 dark:text-white/90">
                                {{ $linha->vendedor->nome }}
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $linha->faturas }} fatura(s)
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($linha->total_cents) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
@endsection
