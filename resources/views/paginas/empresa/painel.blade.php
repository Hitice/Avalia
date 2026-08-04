@extends('layouts.app', ['title' => 'Minha area'])

@php($empresa = auth('empresa')->user())

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $empresa->razao_social }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Area da empresa contratante</p>
    </div>

    {{-- Conta suspensa entra, mas nao consulta. A tela diz o porque em vez de
         so esconder o botao. Cliente sem explicacao liga para o vendedor. --}}
    @if (! $empresa->podeConsultar())
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
            <svg class="mt-0.5 size-5 shrink-0 fill-current" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $empresa->motivoSuspensao() }}</span>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Consultas e faturas aparecem aqui quando os modulos Consulta e Faturamento entrarem.
        </p>
    </div>
@endsection
