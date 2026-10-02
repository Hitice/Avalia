@extends('layouts.app', ['title' => 'Início'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ App\Support\Empresa::marcaVendas() }}</h1>
        <p class="rotulo-grupo mt-1">{{ now()->translatedFormat('F') }}</p>
    </div>

    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Na minha mão" :valor="$emMaos" :href="route('salles.estoque')"
                                   tom="text-brand-600 dark:text-brand-400" />
        <x-avalia.cartao-indicador rotulo="Vendi este mês" :valor="$minhas['placas']" :href="route('etiquetas.index')" />
        <x-avalia.cartao-indicador rotulo="Minha comissão no mês" :valor="Dinheiro::brl($minhas['comissao'])" />
        @if ($ehAdmin)
            <x-avalia.cartao-indicador rotulo="Livres no estoque da casa" :valor="$noBolo" :href="route('etiquetas.criar')" />
        @endif
    </div>

    @if ($ehAdmin)
        <div class="cartao mb-6 p-5">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Equipe no mês</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-avalia.cartao-indicador rotulo="Placas vendidas" :valor="$equipe['placas']" :href="route('plaquinhas.vendas')" />
                <x-avalia.cartao-indicador rotulo="Bruto" :valor="Dinheiro::brl($equipe['bruto'])" :href="route('plaquinhas.vendas')" />
                <x-avalia.cartao-indicador rotulo="Lucro para dividir" :valor="Dinheiro::brl($equipe['lucro'])" :href="route('plaquinhas.vendas')" />
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('etiquetas.index') }}" class="cartao cartao-link p-5">
            <span class="rotulo-grupo block">QR dinâmico</span>
            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">Apontar e vender placas</span>
        </a>
        <a href="{{ route('salles.estoque') }}" class="cartao cartao-link p-5">
            <span class="rotulo-grupo block">Meu estoque</span>
            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">O que está na sua mão</span>
        </a>
        <a href="{{ route('negocios') }}" class="cartao cartao-link p-5">
            <span class="rotulo-grupo block">Negócios</span>
            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">Clientes e link de avaliação</span>
        </a>
        <a href="{{ route('etiquetas.links.index') }}" class="cartao cartao-link p-5">
            <span class="rotulo-grupo block">Encurtador</span>
            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">Links curtos para NFC e impresso</span>
        </a>
    </div>
@endsection