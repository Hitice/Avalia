@extends('layouts.app', ['title' => 'Termos'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Termos da equipe</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Documentos do seu trabalho como vendedor. Os que exigem aceite destravam as demonstrações.
        </p>
    </div>

    @if (session('ok'))
        <div class="aviso aviso-ok mb-6">{{ session('ok') }}</div>
    @endif
    @if (session('erro'))
        <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
    @endif
    @if ($errors->any())
        <div class="aviso aviso-erro mb-6">{{ $errors->first() }}</div>
    @endif

    @if ($documentos->isEmpty())
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">Nenhum termo publicado para a equipe.</p>
        </div>
    @else
        <div class="cartao overflow-hidden">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($documentos as $documento)
                    @php $aceito = in_array($documento->id, $aceites, true); @endphp
                    <li class="px-6 py-5">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="max-w-3xl">
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $documento->titulo }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Versão {{ $documento->versao }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($aceito)
                                    <span class="etiqueta etiqueta-sucesso">Aceito</span>
                                @elseif (! $documento->exige_aceite)
                                    <span class="etiqueta etiqueta-neutra">Leitura e apoio</span>
                                @else
                                    <span class="etiqueta etiqueta-alerta">Aguardando aceite</span>
                                @endif

                                <x-avalia.leitor-termo :documento="$documento" :aceito="$aceito"
                                    :acao="route('termos.aceitar', $documento)" />
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
