@extends('layouts.app', ['title' => 'Início'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <x-avalia.cabecalho-pagina :titulo="App\Support\Empresa::marcaVendas()" :rotulo="now()->translatedFormat('F')" />

    @include('parciais.avisos')

    @if ($ehAdmin && $minhaParte === null)
        {{-- A administracao que nao vende: a casa inteira, em tres linhas que
             cabem na tela. Numeros do mes em cima, estoque e equipe no meio,
             o ritmo embaixo. --}}
        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-avalia.cartao-indicador rotulo="Vendas do mês" :valor="$equipe['placas']" :href="route('plaquinhas.vendas')" tom="text-brand-600 dark:text-brand-400" />
            <x-avalia.cartao-indicador rotulo="Bruto" :valor="Dinheiro::brl($equipe['bruto'])" :href="route('plaquinhas.vendas')" />
            <x-avalia.cartao-indicador rotulo="Lucro" :valor="Dinheiro::brl($equipe['lucro'])" :href="route('plaquinhas.vendas')" />
            <x-avalia.cartao-indicador rotulo="Comissões a pagar" :valor="Dinheiro::brl($aPagar->sum('cents'))" :href="route('plaquinhas.vendas')"
                                       :ajuda="$aPagar->sum('placas').' '.($aPagar->sum('placas') === 1 ? 'placa' : 'placas')" />
        </div>

        <div class="mb-4 grid gap-4 lg:grid-cols-2">
            <div class="cartao p-5">
                <div class="flex items-baseline justify-between">
                    <h2 class="titulo-cartao">Estoque</h2>
                    <a href="{{ route('sales.estoque') }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">ver</a>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div><span class="rotulo-grupo block">Disponíveis</span><span class="text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $disponiveis }}</span></div>
                    <div><span class="rotulo-grupo block">Livres</span><span class="text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $noBolo }}</span></div>
                </div>
                <table class="tabela mt-3">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($emMaos as $lote)
                            <tr>
                                <td class="tabela-td py-2 text-gray-800 dark:text-white/90">{{ $lote->consignadaPara?->nome ?? 'Conta removida' }}</td>
                                <td class="tabela-td py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $lote->total }} em mãos</td>
                            </tr>
                        @empty
                            <tr><td class="tabela-vazia">Ninguém com placa em mãos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="cartao p-5">
                <div class="flex items-baseline justify-between">
                    <h2 class="titulo-cartao">Equipe no mês</h2>
                    <a href="{{ route('plaquinhas.vendas') }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">ver</a>
                </div>
                <table class="tabela mt-3">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($porVendedor as $v)
                            <tr>
                                <td class="tabela-td py-2 text-gray-800 dark:text-white/90">{{ $v['nome'] }}@if ($v['socio']) <span class="etiqueta etiqueta-neutra ml-1">sócio</span>@endif</td>
                                <td class="tabela-td py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $v['placas'] }} {{ $v['placas'] === 1 ? 'placa' : 'placas' }}</td>
                                <td class="tabela-td py-2 text-right tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($v['bruto']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="tabela-vazia">Nenhuma venda no mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cartao p-5">
            <h2 class="titulo-cartao">Vendas por dia</h2>
            <x-avalia.grafico-vendas-dia :por-dia="$porDia" :mes="$mes" />
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-avalia.cartao-indicador rotulo="Placas disponíveis" :valor="$emMaosMinhas" :href="route('sales.estoque')"
                                       tom="text-brand-600 dark:text-brand-400" />
            <x-avalia.cartao-indicador rotulo="Vendas do mês" :valor="$minhas['placas']" :href="route('etiquetas.index')" />
            @if ($minhaParte)
                <x-avalia.cartao-indicador rotulo="Pró-labore do mês" :valor="Dinheiro::brl($minhaParte['prolabore'])"
                                           :ajuda="'Parte '.Dinheiro::brl($minhaParte['parte']).' · retido '.Dinheiro::brl($minhaParte['retido'])" />
                <x-avalia.cartao-indicador rotulo="Lucro da equipe" :valor="Dinheiro::brl($equipe['lucro'])" :href="route('plaquinhas.vendas')" />
            @else
                {{-- Atual e o que ainda nao foi pago, de qualquer mes. --}}
                <x-avalia.cartao-indicador rotulo="Comissão atual" :valor="Dinheiro::brl($comissaoAtual)" ajuda="A receber" />
                <x-avalia.cartao-indicador rotulo="Comissão do mês" :valor="Dinheiro::brl($minhas['comissao'])" />
            @endif
        </div>

        @if ($equipe !== null)
            <div class="cartao mb-6 p-5">
                <h2 class="titulo-cartao">Equipe no mês</h2>
                <table class="tabela mt-3">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($porVendedor as $v)
                            <tr>
                                <td class="tabela-td py-2 text-gray-800 dark:text-white/90">{{ $v['nome'] }}@if ($v['socio']) <span class="etiqueta etiqueta-neutra ml-1">sócio</span>@endif</td>
                                <td class="tabela-td py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $v['placas'] }} {{ $v['placas'] === 1 ? 'placa' : 'placas' }}</td>
                                <td class="tabela-td py-2 text-right tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($v['bruto']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="tabela-vazia">Nenhuma venda no mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <div class="cartao p-5">
            <h2 class="titulo-cartao">{{ $equipe === null ? 'Minhas vendas por dia' : 'Vendas por dia' }}</h2>
            <x-avalia.grafico-vendas-dia :por-dia="$porDia" :mes="$mes" />
        </div>
    @endif
@endsection
