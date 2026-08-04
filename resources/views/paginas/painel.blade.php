@extends('layouts.app', ['title' => 'Painel'])

@php
    $eu = auth('staff')->user();

    // Ordem de construcao, na dependencia real: cliente aponta para plano,
    // consulta aponta para preco, fatura aponta para os dois.
    $modulos = [
        ['nome' => 'Acesso', 'estado' => 'pronto', 'nota' => 'Login, sessao e protecao contra forca bruta'],
        ['nome' => 'Catalogo', 'estado' => 'agora', 'nota' => 'Versoes, precos, planos e franquia; falta a tela de reajuste'],
        ['nome' => 'Cadastro', 'estado' => 'fila', 'nota' => 'Ficha do cliente e situacao contratual'],
        ['nome' => 'Consulta', 'estado' => 'fila', 'nota' => 'Integracao Boa Vista e relatorio'],
        ['nome' => 'Faturamento', 'estado' => 'fila', 'nota' => 'Competencia, fechamento e vencimento'],
        ['nome' => 'Comissao', 'estado' => 'fila', 'nota' => 'Apuracao e repasse ao vendedor'],
        ['nome' => 'Auditoria', 'estado' => 'fila', 'nota' => 'Trilha de alteracoes'],
    ];
    $prontos = count(array_filter($modulos, fn ($m) => $m['estado'] === 'pronto'));
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            Ola, {{ $eu->nome }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $eu->ehAdmin() ? 'Administracao' : 'Carteira de clientes' }}
        </p>
    </div>

    <div class="cartao p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium text-gray-800 dark:text-white/90">Construcao da plataforma</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Cada modulo entra completo, com teste, antes do proximo comecar.
                </p>
            </div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ $prontos }} de {{ count($modulos) }}
            </span>
        </div>

        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($modulos as $m)
                <li class="flex items-center gap-4 py-3">
                    @if ($m['estado'] === 'pronto')
                        <span class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400 flex size-8 shrink-0 items-center justify-center rounded-full">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                    @elseif ($m['estado'] === 'agora')
                        <span class="bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 flex size-8 shrink-0 items-center justify-center rounded-full">
                            <span class="bg-brand-500 size-2 animate-pulse rounded-full"></span>
                        </span>
                    @else
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
                            <span class="size-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                        </span>
                    @endif

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ $m['nome'] }}</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $m['nota'] }}</span>
                    </span>

                    @if ($m['estado'] === 'agora')
                        <span class="bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 rounded-full px-2.5 py-0.5 text-xs font-medium">
                            em construcao
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
