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
                    <li class="px-6 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="max-w-3xl">
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $documento->titulo }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Versão {{ $documento->versao }}</p>
                            </div>

                            {{-- Uma leitura so, o PDF: e o mesmo arquivo que
                                 fica de evidencia, entao o que se le e o que
                                 se aceita. --}}
                            <div class="flex items-center gap-2">
                                <x-avalia.botao variante="secundario" tamanho="sm"
                                                :href="route('empresa.documentos.pdf', $documento)" target="_blank">
                                    Ler documento (PDF)
                                </x-avalia.botao>

                                @if (in_array($documento->id, $aceites, true))
                                    <span class="etiqueta etiqueta-sucesso">Aceito</span>
                                    <x-avalia.botao variante="secundario" tamanho="sm"
                                                    :href="route('empresa.documentos.comprovante', $documento)" target="_blank">
                                        Comprovante
                                    </x-avalia.botao>
                                @elseif (! $documento->exige_aceite)
                                    <span class="etiqueta etiqueta-neutra">Disponível</span>
                                @endif
                            </div>
                        </div>

                        @if ($documento->exige_aceite && ! in_array($documento->id, $aceites, true))
                            {{-- O aceite pede nome, confirmacao de leitura e leva o
                                 hash do que esta na tela: se o texto mudar entre a
                                 leitura e o clique, o servidor recusa e pede
                                 releitura. --}}
                            <form method="POST" action="{{ route('empresa.documentos.aceitar', $documento) }}"
                                  class="mt-4 flex flex-wrap items-end gap-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                                @csrf
                                <input type="hidden" name="hash" value="{{ $documento->hashConteudo() }}">

                                <div class="min-w-64 grow sm:max-w-sm">
                                    <label for="responsavel-{{ $documento->id }}" class="rotulo-campo">Quem está aceitando</label>
                                    <input id="responsavel-{{ $documento->id }}" name="responsavel" type="text"
                                           class="campo" required minlength="5" maxlength="150"
                                           value="{{ old('responsavel') }}" placeholder="Nome completo">
                                </div>

                                <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-sm text-gray-700 select-none dark:text-gray-300">
                                    <input type="checkbox" name="li" value="1" required
                                           class="text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    Li o documento na íntegra
                                </label>

                                <x-avalia.botao tamanho="sm">Aceitar</x-avalia.botao>
                            </form>
                            @error('responsavel') <span class="erro-campo mt-1 block">{{ $message }}</span> @enderror
                            @error('li') <span class="erro-campo mt-1 block">{{ $message }}</span> @enderror
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
