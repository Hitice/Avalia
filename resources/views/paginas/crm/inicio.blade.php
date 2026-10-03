@extends('layouts.app', ['title' => 'Início'])

@section('content')
    <x-avalia.cabecalho-pagina :titulo="App\Support\Empresa::marcaCrm()" rotulo="Quem a casa conhece" />

    @include('parciais.avisos')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Contatos" :valor="$contatos" :href="route('crm.contatos')" tom="text-brand-600 dark:text-brand-400" />
        <x-avalia.cartao-indicador rotulo="Novos no mês" :valor="$novosNoMes" :href="route('crm.contatos')" />
        <x-avalia.cartao-indicador rotulo="Leads novos" :valor="$leadsNovos" :href="route('leads.index')" />
        <x-avalia.cartao-indicador rotulo="Clientes do One" :valor="$porPapel['cliente'] ?? 0" :href="route('crm.contatos', ['papel' => 'cliente'])" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <x-avalia.cartao-indicador rotulo="Negócios do Sales" :valor="$porPapel['negocio'] ?? 0" :href="route('crm.contatos', ['papel' => 'negocio'])" />
        <x-avalia.cartao-indicador rotulo="Produtores do Gestor" :valor="$porPapel['produtor'] ?? 0" :href="route('crm.contatos', ['papel' => 'produtor'])" />
        <x-avalia.cartao-indicador rotulo="Interessados" :valor="$porPapel['interessado'] ?? 0" :href="route('crm.contatos', ['papel' => 'interessado'])" />
    </div>
@endsection
