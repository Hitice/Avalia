@extends('layouts.app', ['title' => 'Área do cliente'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $empresa->razao_social }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Área do cliente</p>
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

    <div class="cartao p-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Consultas e faturas aparecem aqui quando os modulos Consulta e Faturamento entrarem.
        </p>
    </div>

    @if ($documentos->isNotEmpty())
        <div class="cartao mt-6 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Documentos e aceites</h2>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($documentos as $documento)
                    <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white/90">{{ $documento->titulo }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Versão {{ $documento->versao }}</p>
                            <details class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                <summary class="cursor-pointer text-brand-600 dark:text-brand-400">Ler documento</summary>
                                <div class="mt-2 whitespace-pre-line">{{ $documento->conteudo }}</div>
                            </details>
                        </div>
                        @if (in_array($documento->id, $aceites, true))
                            <span class="etiqueta etiqueta-sucesso">Aceito</span>
                        @elseif ($documento->exige_aceite)
                            <form method="POST" action="{{ route('empresa.documentos.aceitar', $documento) }}">
                                @csrf
                                <x-avalia.botao variante="secundario" tamanho="sm">Li e aceito</x-avalia.botao>
                            </form>
                        @else
                            <span class="etiqueta etiqueta-neutra">Disponível</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
