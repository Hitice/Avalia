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
                Demonstração em {{ $consulta->created_at->format('d/m/Y \à\s H:i') }}
                @if ($consulta->referencia_externa)
                    · protocolo {{ $consulta->referencia_externa }}
                @endif
            </p>
        </div>

        @if ($consulta->deuCerto() && ! $consulta->expurgada())
            {{-- O unico jeito aprovado de o resultado sair da tela: arquivo em
                 mao, nunca dado pessoal em URL de conversa. --}}
            <x-avalia.botao variante="secundario" tamanho="sm" :href="route('carteira.demonstracoes.pdf', $consulta)">
                Baixar PDF para o cliente
            </x-avalia.botao>
        @endif
    </div>

    @if (! $consulta->deuCerto())
        <div class="aviso aviso-erro mb-6">
            {{ $resposta['erro'] ?? 'A consulta não foi concluída.' }}
            Nada foi descontado por esta tentativa.
        </div>
    @endif

    @if ($consulta->deuCerto() && ! $consulta->expurgada())
        <div class="cartao overflow-hidden">
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
