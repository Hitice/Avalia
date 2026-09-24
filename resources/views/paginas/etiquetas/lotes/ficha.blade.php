@extends('layouts.ferramenta', ['title' => 'Tiragem '.$lote->codigo])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                Tiragem {{ $lote->codigo }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $lote->titulo }} ·
                {{ $etiquetas->count() }} {{ $etiquetas->count() === 1 ? 'plaquinha' : 'plaquinhas' }}
                @if ($lote->observacao)
                    · {{ $lote->observacao }}
                @endif
            </p>
        </div>

        <a href="{{ route('etiquetas.lotes.index') }}" class="botao botao-secundario">Voltar</a>
    </div>

    @include('paginas.catalogo.avisos')

    {{-- Toda a geracao acontece aqui no navegador. O servidor entregou os
         codigos e nao participa mais: ele nao tem biblioteca de imagem
         instalada, e nao precisa ter, porque o desenho e determinado pelo
         codigo e refazer o pacote em 2031 da o mesmo resultado. --}}
    <div x-data="tiragem(@js(['pasta' => $lote->pasta(), 'etiquetas' => $etiquetas]))"
         class="grid gap-5 lg:grid-cols-[22rem_1fr]">

        <div class="cartao p-6">
            <h2 class="rotulo-grupo">Prova</h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                O primeiro código da tiragem, no tamanho declarado. Leia com o celular antes de
                mandar cortar: a tela aproxima a medida, mas a leitura é a de verdade.
            </p>

            {{-- Branco tambem no tema escuro, de proposito: a prova mostra o
                 que vai sair impresso, e o codigo sai sobre placa branca. Um
                 QR num quadro escuro leria pior na tela do que le na placa, e
                 a prova passaria a mentir sobre o proprio produto. --}}
            <div class="mt-5 flex justify-center rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white">
                <div x-ref="prova"></div>
            </div>

            @if ($etiquetas->isNotEmpty())
                <p class="mt-3 text-center font-mono text-sm tracking-widest text-gray-600 dark:text-gray-300">
                    {{ $etiquetas->first()['codigo'] }}
                </p>
            @endif

            <div class="mt-6">
                <label for="lote-mm" class="rotulo-campo">Lado do código, em milímetros</label>
                <input id="lote-mm" type="number" min="10" max="200" x-model.number="mm" class="campo">
                <p class="ajuda-campo">
                    É o tamanho com que o arquivo cai no CorelDRAW, já com a zona de silêncio
                    incluída no desenho.
                </p>
            </div>

            <label class="mt-4 flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" x-model="logo" class="size-4 rounded border-gray-300">
                Marca da Avalia no miolo
            </label>
        </div>

        <div class="cartao p-6">
            <h2 class="rotulo-grupo">Pacote da tiragem</h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Um SVG e um PNG por plaquinha, numerados pela sequência, mais o CSV que o Print
                Merge do CorelDRAW lê para numerar e trocar o QR na tiragem inteira.
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-avalia.botao x-on:click="baixar()" x-bind:disabled="gerando">
                    <span x-show="! gerando">Baixar a tiragem</span>
                    <span x-show="gerando" x-cloak>Montando…</span>
                </x-avalia.botao>

                <span x-show="gerando" x-cloak class="text-sm text-gray-500 tabular-nums dark:text-gray-400">
                    <span x-text="feito"></span> de {{ $etiquetas->count() }}
                </span>
            </div>

            <p x-show="erro" x-cloak x-text="erro" class="aviso aviso-erro mt-4"></p>

            <h3 class="rotulo-grupo mt-8">Códigos</h3>

            <div class="tabela-rolagem mt-3 max-h-[26rem]">
                <table class="tabela">
                    <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                        <tr>
                            <th scope="col" class="tabela-th text-left">Nº</th>
                            <th scope="col" class="tabela-th text-left">Código</th>
                            <th scope="col" class="tabela-th text-left">Arquivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($etiquetas as $etiqueta)
                            <tr>
                                <td class="tabela-td tabular-nums">{{ $etiqueta['sequencia'] }}</td>
                                <td class="tabela-td font-mono tracking-wider">{{ $etiqueta['codigo'] }}</td>
                                <td class="tabela-td text-gray-500 dark:text-gray-400">{{ $etiqueta['arquivo'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
