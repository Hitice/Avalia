@extends('layouts.app', ['title' => 'Consulta'])

@php
    use App\Support\Dinheiro;

    $resposta = $consulta->resposta ?? [];
@endphp

@section('content')
    <a href="{{ route('empresa.painel') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Painel
    </a>

    <x-avalia.ajuda class="float-right" assunto="Consulta" :referencia="$consulta->referencia_externa">
        Dúvida sobre esta consulta
    </x-avalia.ajuda>

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $consulta->servico->nome }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Consultado em {{ $consulta->created_at->format('d/m/Y \à\s H:i') }}
            @if ($consulta->referencia_externa)
                · protocolo {{ $consulta->referencia_externa }}
            @endif
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    {{-- Rever o resultado guardado nao custa nada: o dado ja esta aqui e
         ninguem paga o fornecedor de novo. O que custa e perguntar de novo,
         e a tela diz isso com todas as letras, com a data do que esta sendo
         lido, para ninguem confundir dado antigo com dado de hoje. --}}
    @if ($consulta->deuCerto() && ! $consulta->expurgada())
        <div class="aviso {{ $consulta->created_at->isToday() ? 'aviso-ok' : 'aviso-alerta' }} mb-6 flex flex-wrap items-center justify-between gap-3">
            <span>
                @if ($consulta->created_at->isToday())
                    Resultado desta consulta, sem custo adicional para rever.
                @else
                    Você está vendo o resultado de {{ $consulta->created_at->format('d/m/Y') }}.
                    A situação pode ter mudado desde então.
                @endif
            </span>

            <x-avalia.botao variante="secundario" tamanho="sm" :href="route('empresa.consultar')">
                Consultar novamente
            </x-avalia.botao>
        </div>
    @endif

    @if ($consulta->expurgada())
        <div class="aviso aviso-alerta mb-6">
            O resultado desta consulta foi apagado após o prazo de retenção de
            {{ App\Models\Consulta::DIAS_DE_RETENCAO }} dias. O registro da consulta permanece.
        </div>
    @elseif (! $consulta->deuCerto())
        <div class="aviso aviso-erro mb-6">
            {{ $resposta['erro'] ?? 'A consulta não foi concluída.' }}
            Nada foi cobrado por esta tentativa.
        </div>
    @endif

    <div class="cartao p-6">
        <dl class="grid gap-5 sm:grid-cols-2">
            <div>
                <dt class="rotulo-grupo">Finalidade declarada</dt>
                <dd class="mt-1 text-gray-800 dark:text-white/90">{{ $consulta->finalidade }}</dd>
            </div>

            @if ($consulta->solicitante)
                <div>
                    <dt class="rotulo-grupo">Solicitante</dt>
                    <dd class="mt-1 text-gray-800 dark:text-white/90">{{ $consulta->solicitante }}</dd>
                </div>
            @endif

            <div>
                <dt class="rotulo-grupo">Valor</dt>
                <dd class="mt-1 text-gray-800 dark:text-white/90">
                    {{ $consulta->preco_cents > 0 ? Dinheiro::brl($consulta->preco_cents) : 'Sem cobrança' }}
                </dd>
            </div>

            <div>
                <dt class="rotulo-grupo">Tempo de resposta</dt>
                <dd class="mt-1 text-gray-800 dark:text-white/90">
                    {{ $consulta->duracao_ms ? $consulta->duracao_ms.' ms' : 'Não informado' }}
                </dd>
            </div>
        </dl>
    </div>

    @if ($consulta->deuCerto() && ! $consulta->expurgada())
        <div class="cartao mt-6 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Resultado</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="tabela min-w-[32rem]">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($resposta as $campo => $valor)
                            <tr>
                                <th scope="row" class="px-6 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                                    {{ ucfirst(str_replace('_', ' ', $campo)) }}
                                </th>
                                <td class="px-6 py-3 text-right text-gray-800 dark:text-white/90">
                                    @if (is_bool($valor))
                                        {{ $valor ? 'Sim' : 'Não' }}
                                    @elseif (str_ends_with($campo, '_cents'))
                                        {{ Dinheiro::brl((int) $valor) }}
                                    @else
                                        {{ is_scalar($valor) ? $valor : json_encode($valor) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
