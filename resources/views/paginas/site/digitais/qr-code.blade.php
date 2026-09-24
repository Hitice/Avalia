@php
    use App\Support\Dinheiro;
@endphp

@extends('layouts.site', [
    'titulo' => 'Gerador de QR Code',
    'descricao' => 'Gere um QR Code e baixe em SVG vetorial ou PNG, no tamanho em milímetros que precisar. Sem cadastro e sem marca d\'água.',
    'secao' => 'digitais.index',
])

@section('content')
    <x-site.cabecalho selo="Grátis" :titulo="$servico['titulo']" :icone="$servico['icone']">
        {{ $servico['resumo'] }} O desenho acontece no seu navegador: o endereço que você digitar
        não é enviado para lugar nenhum.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[64rem] px-6">
            <div x-data="gerador" class="grid gap-8 lg:grid-cols-[1fr_20rem]">
                <div class="cartao p-7">
                    <div class="campo-linha">
                        <label for="conteudo" class="rotulo-campo">Endereço ou texto</label>
                        <input id="conteudo" type="text" x-model.debounce.300ms="conteudo" class="campo"
                               placeholder="https://seusite.com.br">
                        <p class="ajuda-campo">
                            Vale link, texto, telefone ou qualquer coisa que o leitor deva mostrar.
                        </p>
                    </div>

                    <div class="campo-linha">
                        <label for="tamanho" class="rotulo-campo">Lado do código, em milímetros</label>
                        <input id="tamanho" type="number" min="10" max="200" x-model.number="mm" class="campo">
                        <p class="ajuda-campo">
                            É o tamanho com que o SVG cai no CorelDRAW ou no Illustrator, já com a
                            margem branca que o código precisa para ser lido.
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <x-avalia.botao x-on:click="baixarSvg()" x-bind:disabled="! conteudo">SVG (vetor)</x-avalia.botao>
                        <button type="button" x-on:click="baixarPng()" x-bind:disabled="! conteudo"
                                class="botao botao-secundario">PNG</button>
                    </div>
                </div>

                <div class="cartao p-7">
                    <h2 class="rotulo-grupo">Prévia</h2>

                    <div class="mt-4 flex min-h-[14rem] items-center justify-center rounded-xl border border-gray-200 bg-white p-4">
                        <div x-ref="prova"></div>
                        <p x-show="! conteudo" x-cloak class="text-sm text-gray-400">Digite algo para ver o código.</p>
                    </div>
                </div>
            </div>

            {{-- A diferenca entre estatico e dinamico, dita onde ela importa:
                 depois de a pessoa ter gerado o codigo e antes de ela mandar
                 imprimir mil deles. --}}
            <div class="cartao mt-8 flex flex-col items-start gap-6 p-8 lg:flex-row lg:items-center lg:justify-between" data-revelar>
                <div class="max-w-2xl">
                    <h2 class="text-xl font-semibold text-gray-900">Este código é estático</h2>
                    <p class="mt-2 leading-relaxed text-gray-600">
                        Ele leva para sempre ao endereço que você digitou. Se um dia esse endereço
                        mudar, não há conserto: os códigos já impressos param de servir e tudo é
                        reimpresso. O QR dinâmico resolve isso por
                        {{ Dinheiro::brl((int) config('etiquetas.precos.avulso_mensal_cents')) }} por mês,
                        e já vem incluso nas nossas plaquinhas.
                    </p>
                </div>

                <x-avalia.botao :href="route('digitais.plaquinhas')" class="shrink-0">
                    Ver as plaquinhas
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </x-avalia.botao>
            </div>
        </div>
    </section>
@endsection
