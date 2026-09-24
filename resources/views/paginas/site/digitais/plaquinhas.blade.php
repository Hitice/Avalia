@php
    use App\Support\Dinheiro;
    use App\Support\Empresa;

    $precos = config('etiquetas.precos');
@endphp

@extends('layouts.site', [
    'titulo' => 'Gerador de QR Code dinâmico',
    'descricao' => 'QR Code cujo destino muda depois de impresso. Gere, mande imprimir, venda, e só então diga para onde cada código leva.',
    // A aba do menu continua marcada nas paginas de dentro da frente.
    'secao' => 'digitais.index',
])

@section('content')
    <x-site.cabecalho selo="Serviços digitais" :titulo="$servico['titulo']" :icone="$servico['icone']">
        {{ $servico['resumo'] }}

        <x-slot:rodape>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-avalia.botao :href="route('site.contato')">
                    Quero usar
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </x-avalia.botao>

                <a href="{{ route('digitais.qr') }}" class="botao border border-white/20 text-white transition hover:bg-white/10">
                    Gerar um QR grátis
                </a>
            </div>
        </x-slot:rodape>
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="grid gap-10 lg:grid-cols-[1fr_24rem] lg:gap-14">
                <div class="prosa">
                    <p>{{ $servico['texto'] }}</p>
                    <p>
                        A diferença para um QR comum é só uma, e é a que importa: o código comum
                        carrega o endereço do cliente dentro do próprio desenho, então trocar de
                        endereço é reimprimir tudo. Aqui o desenho carrega um endereço nosso, e o
                        do cliente fica num campo que a gente edita.
                    </p>
                </div>

                {{-- O preco fica ao lado do texto, e nao no fim da pagina:
                     e produto de tabela, e quem abre esta pagina veio saber
                     quanto custa. --}}
                <aside class="cartao h-fit p-7">
                    <h2 class="rotulo-grupo">Quanto custa</h2>

                    <p class="mt-4 text-3xl font-semibold tracking-tight text-gray-900">
                        {{ Dinheiro::brl($precos['placa_cents']) }}
                        <span class="text-base font-normal text-gray-500">por código</span>
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">
                        O código e o primeiro ano de serviço. Depois,
                        {{ Dinheiro::brl($precos['renovacao_cents']) }} por ano para o endereço
                        continuar de pé.
                    </p>

                    <ul class="mt-6 space-y-3 border-t border-gray-100 pt-6 text-sm leading-relaxed text-gray-600">
                        @foreach ([
                            'Trocas de destino sem limite, e sem reimprimir',
                            'SVG em vetor e PNG, no tamanho em milímetros que você pedir',
                            'Contagem de leituras, por dia',
                            'O código é seu e nunca vai para outro cliente',
                        ] as $item)
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 size-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </aside>
            </div>

            <h2 class="mt-16 text-title-sm font-semibold tracking-tight text-gray-900 lg:mt-24">Como funciona</h2>

            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['01', 'Gere os códigos', 'Um, ou cem de uma vez. Saem em SVG e PNG, numerados, com o CSV que o CorelDRAW lê para imprimir a tiragem inteira.'],
                    ['02', 'Imprima e venda', 'Os códigos nascem em branco, sem destino. É essa a ideia: imprimir antes de saber para quem vai.'],
                    ['03', 'Cadastre o destino', 'Vendeu, leu o código impresso e informou a URL. Mudou depois? Troca o campo, e a placa que já está no balcão passa a levar para o lugar novo.'],
                ] as [$numero, $titulo, $texto])
                    <div class="bloco p-7" data-revelar>
                        <span class="indice indice-claro">{{ $numero }}</span>
                        <h3 class="mt-4 text-lg font-semibold tracking-tight text-gray-900">{{ $titulo }}</h3>
                        <p class="mt-3 leading-relaxed text-gray-600">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-site.chamada titulo="Quer os códigos no seu balcão?" acao="Falar com a Avalia">
        Diga quantos você precisa e para onde eles devem apontar. A gente gera, entrega e deixa
        funcionando.
    </x-site.chamada>
@endsection
