@extends('layouts.app', ['title' => 'Início'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <x-avalia.cabecalho-pagina :titulo="App\Support\Empresa::marcaVendas()" :rotulo="now()->translatedFormat('F')" />

    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Placas disponíveis" :valor="$emMaos" :href="route('sales.estoque')"
                                   tom="text-brand-600 dark:text-brand-400" />
        <x-avalia.cartao-indicador rotulo="Vendas do mês" :valor="$minhas['placas']" :href="route('etiquetas.index')" />
        @if ($minhaParte)
            <x-avalia.cartao-indicador rotulo="Meu pró-labore no mês" :valor="Dinheiro::brl($minhaParte['prolabore'])"
                                       :ajuda="'Minha parte é '.Dinheiro::brl($minhaParte['parte']).'; '.Dinheiro::brl($minhaParte['retido']).' ficam na empresa'" />
        @else
            {{-- Atual e o que ainda nao foi pago, de qualquer mes; zera na sexta. --}}
            <x-avalia.cartao-indicador rotulo="Comissão atual" :valor="Dinheiro::brl($comissaoAtual)" ajuda="A receber" />
            <x-avalia.cartao-indicador rotulo="Comissão do mês" :valor="Dinheiro::brl($minhas['comissao'])" />
        @endif
        @if ($ehAdmin)
            <x-avalia.cartao-indicador rotulo="Livres no estoque da casa" :valor="$noBolo" :href="route('etiquetas.criar')" />
        @endif
    </div>

    @if ($ehAdmin)
        <div class="cartao mb-6 p-5">
            <h2 class="titulo-secao">Equipe no mês</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-avalia.cartao-indicador rotulo="Placas vendidas" :valor="$equipe['placas']" :href="route('plaquinhas.vendas')" />
                <x-avalia.cartao-indicador rotulo="Bruto" :valor="Dinheiro::brl($equipe['bruto'])" :href="route('plaquinhas.vendas')" />
                <x-avalia.cartao-indicador rotulo="Lucro para dividir" :valor="Dinheiro::brl($equipe['lucro'])" :href="route('plaquinhas.vendas')" />
            </div>
        </div>
    @endif

    <div class="cartao p-6">
        <h2 class="titulo-cartao">{{ $equipe === null ? 'Minhas vendas por dia' : 'Vendas por dia' }}</h2>
        <x-avalia.grafico-vendas-dia :por-dia="$porDia" :mes="$mes" />
    </div>
@endsection
