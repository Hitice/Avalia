@extends('layouts.app', ['title' => 'Demonstração'])

@php
    use App\Support\Dinheiro;

    $resposta = $consulta->resposta ?? [];
@endphp

@section('content')
    <a href="{{ route('carteira.consultar') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Consultar
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $consulta->servico->nome }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Consultado em {{ $consulta->created_at->format('d/m/Y \à\s H:i') }}
                @if ($consulta->referencia_externa)
                    · protocolo {{ $consulta->referencia_externa }}
                @endif
            </p>
        </div>

        @if ($consulta->deuCerto() && ! $consulta->expurgada())
            {{-- O unico jeito aprovado de o resultado sair da tela: arquivo em
                 mao, nunca dado pessoal em URL de conversa. --}}
            <x-avalia.visor-laudo :url="route('carteira.demonstracoes.pdf', $consulta)"
                                  :aberto="request('laudo') === 'aberto'" />
        @endif
    </div>

    @if (! $consulta->deuCerto())
        <div class="aviso aviso-erro mb-6">
            {{ $resposta['erro'] ?? 'A consulta não foi concluída.' }}
            Nada foi descontado por esta tentativa.
        </div>
    @endif

    @if ($consulta->deuCerto() && ! $consulta->expurgada())
        @include('paginas.consulta-blocos', ['resposta' => $resposta, 'documento' => $consulta->documento])
    @endif
@endsection
