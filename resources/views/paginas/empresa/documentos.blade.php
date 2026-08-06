@extends('layouts.app', ['title' => 'Documentos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Documentos e aceites</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $empresa->razao_social }}</p>
        </div>
        <x-avalia.ajuda assunto="Documentos">Falar com a Avalia</x-avalia.ajuda>
    </div>

    @if ($documentos->isEmpty())
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Solicite à sua equipe comercial os documentos aplicáveis à sua empresa.
            </p>
        </div>
    @else
        <div class="cartao overflow-hidden">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($documentos as $documento)
                    <li class="flex flex-wrap items-start justify-between gap-4 px-6 py-5">
                        <div class="max-w-3xl">
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
