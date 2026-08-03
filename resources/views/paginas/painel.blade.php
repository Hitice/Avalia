@extends('layouts.app', ['title' => 'Painel'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            Ola, {{ auth('staff')->user()->nome }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ auth('staff')->user()->ehAdmin() ? 'Administracao' : 'Carteira de clientes' }}
            @if (auth('staff')->user()->ehSuper())
                <span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">super</span>
            @endif
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <h2 class="text-sm font-medium text-gray-800 dark:text-white/90">Modulo Acesso concluido</h2>
        <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
            Login com dois guards, revogacao de sessao e protecao contra forca bruta estao no ar.
            Os proximos motores entram nesta ordem: Catalogo (planos e precos), Cadastro (ficha do
            cliente), Consulta (Boa Vista), Faturamento e Comissao.
        </p>
        <a href="{{ route('kit.painel') }}"
            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
            Ver componentes disponiveis
        </a>
    </div>
@endsection
