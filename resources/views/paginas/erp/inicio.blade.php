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

    @if (auth('staff')->user()?->podeFinanceiro())
        {{-- Producao nao tem SSH: o que era comando passa pela tela. Tudo aqui
             e idempotente, entao repetir nao duplica. --}}
        <div class="cartao mt-6 flex flex-wrap items-center justify-between gap-3 p-5">
            <div>
                <h2 class="titulo-cartao">Razão</h2>
                <p class="subtitulo-pagina">Lança o que falta e relança o que mudou de regra</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('erp.razao.conciliar') }}">
                    @csrf
                    <x-avalia.botao variante="secundario">Conciliar razão</x-avalia.botao>
                </form>
                @if (auth('staff')->user()?->podeSocios())
                    <form method="POST" action="{{ route('erp.razao.apagar-aportes') }}" onsubmit="return confirm('Apagar todos os aportes de capital? Não se desfaz.')">
                        @csrf
                        <x-avalia.botao variante="secundario">Apagar aportes</x-avalia.botao>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
