@extends('layouts.app', ['title' => 'Início'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <x-avalia.cabecalho-pagina :titulo="App\Support\Empresa::marcaErp()" :rotulo="now()->translatedFormat('F')" />

    @include('parciais.avisos')

    {{-- O que se olha ao abrir: dinheiro em conta e o movimento do mes, lidos
         do razao. Sem atalhos aqui: a lateral ja os tem. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Caixa" :valor="Dinheiro::brl($saldo)" :href="route('socios.index')"
                                   tom="text-brand-600 dark:text-brand-400" />
        <x-avalia.cartao-indicador rotulo="Entradas no mês" :valor="Dinheiro::brl($entradas)" :href="route('socios.index')" />
        <x-avalia.cartao-indicador rotulo="Saídas no mês" :valor="Dinheiro::brl($saidas)" :href="route('socios.index')" />
        <x-avalia.cartao-indicador rotulo="Comissões a pagar" :valor="$placasAPagar" :href="route('plaquinhas.vendas')" />
    </div>
@endsection
