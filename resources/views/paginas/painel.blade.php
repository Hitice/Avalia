@extends('layouts.app', ['title' => 'Visão geral'])
@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            Olá, {{ $staff->nome }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $staff->ehAdmin() ? 'Resumo da operação' : 'Resumo da sua carteira' }} · {{ $competencia }}
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="cartao p-5"><span class="rotulo-grupo block">Empresas ativas</span><span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $clientesAtivos }}</span></div>
        <div class="cartao p-5"><span class="rotulo-grupo block">Consultas no período</span><span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $consultas }}</span></div>
        <div class="cartao p-5"><span class="rotulo-grupo block">A receber</span><span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ Dinheiro::brl($aReceber) }}</span></div>
        <div class="cartao p-5"><span class="rotulo-grupo block">Em atraso</span><span class="mt-1 block text-2xl font-semibold text-error-600 dark:text-error-400">{{ Dinheiro::brl($vencido) }}</span></div>
        <div class="cartao p-5"><span class="rotulo-grupo block">Empresas inadimplentes</span><span class="mt-1 block text-2xl font-semibold text-error-600 dark:text-error-400">{{ $inadimplentes }}</span></div>
        <div class="cartao p-5"><span class="rotulo-grupo block">Comissão liberada</span><span class="mt-1 block text-2xl font-semibold text-success-600 dark:text-success-500">{{ Dinheiro::brl($comissaoLiberada) }}</span></div>
    </div>
@endsection
