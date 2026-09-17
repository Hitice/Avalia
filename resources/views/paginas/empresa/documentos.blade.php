@extends('layouts.app', ['title' => 'Documentos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Documentos e aceites</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $empresa->razao_social }}</p>
        </div>
        <x-avalia.ajuda assunto="Documentos">Falar com a Avalia One</x-avalia.ajuda>
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
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Solicite à sua equipe comercial os documentos aplicáveis à sua empresa.
            </p>
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
                                    {{-- Material de apoio: leitura livre, sem aceite. --}}
                                    <span class="etiqueta etiqueta-neutra">Leitura e apoio</span>
                                @else
                                    <span class="etiqueta etiqueta-alerta">Aguardando aceite</span>
                                @endif

                                {{-- A leitura abre em popup com rolagem vigiada e o
                                     aceite destrava no fim, como termo de aparelho
                                     novo. O PDF continua sendo o arquivo de evidencia. --}}
                                <x-avalia.leitor-termo :documento="$documento" :aceito="$aceito"
                                    :acao="route('empresa.documentos.aceitar', $documento)" />

                                <x-avalia.botao variante="secundario" tamanho="sm"
                                                :href="route('empresa.documentos.pdf', $documento)" target="_blank">
                                    PDF
                                </x-avalia.botao>

                                @if ($aceito)
                                    <x-avalia.botao variante="secundario" tamanho="sm"
                                                    :href="route('empresa.documentos.comprovante', $documento)" target="_blank">
                                        Comprovante
                                    </x-avalia.botao>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
