@extends('layouts.site', [
    'titulo' => 'Área do produtor',
    'descricao' => 'Entradas das plataformas da casa: pesquisa de score para venda a prazo e venda parcelada com cobrança automática.',
    'secao' => 'area',
])

@php
    use App\Support\Empresa;
@endphp

@section('content')
    <x-site.cabecalho selo="Acesso" icone="M15 3h4a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3" titulo="Área do produtor">
        As plataformas da casa têm contas separadas, uma para cada operação. Escolha por onde entrar.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            {{-- Quem chega com sessao aberta ve primeiro o proprio painel.

                 A porta certa para quem ja entrou nao e a lista de portas: e o
                 lugar onde o trabalho dele esta. As duas entradas continuam
                 logo abaixo, porque a mesma pessoa pode operar os dois
                 negocios com contas diferentes. --}}
            @if ($sessoes !== [])
                <div class="cartao mb-10 flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-gray-600">
                        <strong class="font-semibold text-gray-900">Você já está conectado.</strong>
                        Continue de onde parou.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($sessoes as $sessao)
                            <x-avalia.botao :href="$sessao['href']">
                                {{ $sessao['rotulo'] }}
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </x-avalia.botao>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Porta do score. --}}
                <div class="cartao flex flex-col p-8" data-revelar>
                    <x-avalia.logotipo :tamanho="38" texto="1.35rem" marca="credito" />

                    <h2 class="mt-6 text-xl font-semibold text-gray-900">Pesquisa de score para venda a prazo</h2>
                    <p class="mt-3 leading-relaxed text-gray-600">
                        Para quem vende parcelado e precisa saber com quem está negociando antes de
                        fechar. Pesquisa de score e dados públicos, com o resultado em segundos.
                    </p>

                    <ul class="mt-6 space-y-2.5 text-sm text-gray-600">
                        @foreach (['Pesquisa de score e de dados públicos', 'Laudo com protocolo e data', 'Equipe com acessos separados'] as $item)
                            <li class="flex items-start gap-2.5">
                                <svg class="mt-0.5 size-4 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 flex flex-wrap gap-3 pt-2">
                        <x-avalia.botao :href="route('entrar')">
                            Entrar
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a1 1 0 011 1v16a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3" />
                            </svg>
                        </x-avalia.botao>

                        <x-avalia.botao variante="secundario" :href="route('credito')">
                            Conhecer o {{ Empresa::marcaCredito() }}
                        </x-avalia.botao>
                    </div>
                </div>

                {{-- Porta da cobranca. --}}
                <div class="cartao flex flex-col p-8" data-revelar style="--atraso: 0.1s">
                    <x-avalia.logotipo :tamanho="38" texto="1.35rem" marca="cobranca" />

                    <h2 class="mt-6 text-xl font-semibold text-gray-900">Venda parcelada, cobrança e APIs</h2>
                    <p class="mt-3 leading-relaxed text-gray-600">
                        Para quem quer parcelar a própria venda em boleto e Pix sem depender do cartão
                        do cliente, com a régua de cobrança e o repasse acontecendo sozinhos.
                    </p>

                    <ul class="mt-6 space-y-2.5 text-sm text-gray-600">
                        @foreach (['Parcelamento em boleto e Pix', 'Régua de cobrança automática', 'Repasse a cada parcela paga'] as $item)
                            <li class="flex items-start gap-2.5">
                                <svg class="mt-0.5 size-4 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 flex flex-wrap gap-3 pt-2">
                        <x-avalia.botao :href="route('produtor.entrar')">
                            Entrar
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a1 1 0 011 1v16a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3" />
                            </svg>
                        </x-avalia.botao>

                        <x-avalia.botao variante="secundario" :href="route('produtor.criar-conta')">
                            Criar conta
                        </x-avalia.botao>
                    </div>
                </div>
            </div>

            <p class="mt-8 text-center text-sm text-gray-500">
                Ainda não é cliente?
                <a href="{{ route('site.contato') }}" class="font-medium text-brand-600 hover:text-brand-700">Fale com a {{ Empresa::marca() }}</a>
                e descubra qual das plataformas atende a sua operação.
            </p>
        </div>
    </section>
@endsection
