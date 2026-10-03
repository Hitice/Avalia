@extends('layouts.app', ['title' => 'Início'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <x-avalia.cabecalho-pagina :titulo="App\Support\Empresa::marcaVendas()" :rotulo="now()->translatedFormat('F')" />

    @include('parciais.avisos')

    {{-- Quem vende ve o que e seu; o socio, o pro-labore; a administracao que
         nao e socia ve so a casa. --}}
    @php $pessoal = ! $ehAdmin || $minhaParte !== null; @endphp
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($pessoal)
            <x-avalia.cartao-indicador rotulo="Placas disponíveis" :valor="$emMaos" :href="route('sales.estoque')"
                                       tom="text-brand-600 dark:text-brand-400" />
            <x-avalia.cartao-indicador rotulo="Vendas do mês" :valor="$minhas['placas']" :href="route('etiquetas.index')" />
        @endif
        @if ($minhaParte)
            <x-avalia.cartao-indicador rotulo="Pró-labore do mês" :valor="Dinheiro::brl($minhaParte['prolabore'])"
                                       :ajuda="'Parte '.Dinheiro::brl($minhaParte['parte']).' · retido '.Dinheiro::brl($minhaParte['retido'])" />
        @elseif ($pessoal)
            {{-- Atual e o que ainda nao foi pago, de qualquer mes; zera na sexta. --}}
            <x-avalia.cartao-indicador rotulo="Comissão atual" :valor="Dinheiro::brl($comissaoAtual)" ajuda="A receber" />
            <x-avalia.cartao-indicador rotulo="Comissão do mês" :valor="Dinheiro::brl($minhas['comissao'])" />
        @endif
        @if ($ehAdmin)
            <x-avalia.cartao-indicador rotulo="Estoque atual" :valor="$noBolo" :href="route('etiquetas.criar')" />
        @endif
    </div>

    @if ($ehAdmin)
        <div class="cartao mb-6 p-5">
            <h2 class="titulo-secao">Equipe no mês</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-avalia.cartao-indicador rotulo="Placas vendidas" :valor="$equipe['placas']" :href="route('plaquinhas.vendas')" />
                <x-avalia.cartao-indicador rotulo="Bruto" :valor="Dinheiro::brl($equipe['bruto'])" :href="route('plaquinhas.vendas')" />
                <x-avalia.cartao-indicador rotulo="Lucro" :valor="Dinheiro::brl($equipe['lucro'])" :href="route('plaquinhas.vendas')" />
            </div>

            <table class="tabela mt-5">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($porVendedor as $v)
                        <tr>
                            <td class="tabela-td text-gray-800 dark:text-white/90">{{ $v['nome'] }}@if ($v['socio']) <span class="etiqueta etiqueta-neutra ml-1">sócio</span>@endif</td>
                            <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $v['placas'] }} {{ $v['placas'] === 1 ? 'placa' : 'placas' }}</td>
                            <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($v['bruto']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="tabela-vazia">Nenhuma venda no mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="cartao p-6">
        <h2 class="titulo-cartao">{{ $equipe === null ? 'Minhas vendas por dia' : 'Vendas por dia' }}</h2>
        <x-avalia.grafico-vendas-dia :por-dia="$porDia" :mes="$mes" />
    </div>
@endsection
