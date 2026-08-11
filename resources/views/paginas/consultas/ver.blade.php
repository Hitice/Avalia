@extends('layouts.app', ['title' => 'Consulta'])

@php
    use App\Support\Dinheiro;

    $resposta = $consulta->resposta ?? [];
@endphp

@section('content')
    <a href="{{ route('consultas') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <x-avalia.icone nome="voltar" />
        Consultas
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                {{ $consulta->servico?->nome ?? 'Serviço descontinuado' }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $consulta->cliente?->razao_social ?? 'Consulta da casa' }}
                · {{ $consulta->created_at->format('d/m/Y \à\s H:i') }}
                @if ($consulta->referencia_externa)
                    · protocolo {{ $consulta->referencia_externa }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-avalia.botao variante="secundario" tamanho="sm" :href="route('consultas')">
                Voltar
            </x-avalia.botao>

            <x-avalia.visor-laudo :url="route('consultas.pdf', $consulta)" rotulo="Ver laudo" />
        </div>
    </div>

    {{-- O aviso existe porque esta tela é a única em que a administração vê o
         dado de um titular que não é cliente dela. Dizer que a abertura ficou
         registrada não é ameaça: é o que faz a trilha ser levada a sério por
         quem opera, e é o que se responde ao titular que perguntar. --}}
    <div class="aviso aviso-alerta mb-6">
        Esta abertura ficou registrada na trilha, com o seu nome e a data.
        Use o resultado apenas para atender o cliente, e não o repasse.
    </div>

    <div class="cartao mb-6 p-6">
        <dl class="grid gap-5 sm:grid-cols-2">
            <div>
                <dt class="rotulo-grupo">Documento consultado</dt>
                <dd class="mt-1 text-gray-800 dark:text-white/90">
                    {{ App\Support\Documento::mascarar($consulta->documento) ?: 'Não informado' }}
                </dd>
            </div>

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
                <dt class="rotulo-grupo">Valor cobrado</dt>
                <dd class="mt-1 text-gray-800 dark:text-white/90">
                    {{ $consulta->preco_cents > 0 ? Dinheiro::brl($consulta->preco_cents) : 'Sem cobrança' }}
                </dd>
            </div>
        </dl>
    </div>

        {{-- O conteudo do resultado vive no RELATORIO, que abre no visor por
             cima desta pagina. Repetir os blocos aqui atras do popup era ler o
             mesmo laudo duas vezes em duas diagramacoes, e a tela por baixo
             existe so para reabrir o visor e navegar. --}}
@endsection
