@extends('layouts.site', [
    'titulo' => 'Softwares',
    'descricao' => 'Automação de processos, chat e atendimento humanizados, análise de mercado, automação de cobranças, integração de sistemas, controle de produção com CRM e desenvolvimento de sites, SaaS e web apps.',
])

@section('content')
    <x-site.cabecalho selo="Soluções" icone="M4 6.5h6v6H4zM14 6.5h6v6h-6zM4 16h6v4H4zM14 16h6v4h-6z" titulo="Softwares para cada rotina da sua operação">
        Todo projeto começa pelo seu processo. Escolha uma frente ou combine várias: todas se
        integram entre si e aos sistemas que você já usa.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] space-y-16 px-6 lg:space-y-24">
            @foreach ($softwares as $ancora => $frente)
                {{-- As colunas se alternam a cada frente. Sete blocos iguais
                     empilhados viram uma coluna so aos olhos de quem rola; o
                     zigue-zague marca onde um assunto termina e o outro
                     comeca, sem precisar de mais uma linha divisoria. --}}
                <article id="{{ $ancora }}" data-revelar
                         @class([
                             'grid scroll-mt-20 items-start gap-8 lg:gap-14',
                             // Sem foto nao ha segunda coluna: a frente ocupa a
                             // largura inteira em vez de deixar metade vazia.
                             'lg:grid-cols-2' => $frente['imagem'],
                         ])>
                    <div class="{{ $loop->even ? 'lg:order-last' : '' }}">
                        <span class="icone-caixa">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $frente['icone'] }}" />
                            </svg>
                        </span>
                        <h2 class="mt-5 text-title-sm font-semibold tracking-tight text-gray-900">{{ $frente['titulo'] }}</h2>
                        <p class="mt-4 leading-relaxed text-gray-600">{{ $frente['texto'] }}</p>

                        <x-avalia.botao :href="route('site.contato')" class="mt-6">
                            Solicitar proposta
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </x-avalia.botao>
                        {{-- A lista fica com o texto, e nao embaixo da foto.
                             Na coluna da imagem ela empurrava o bloco para
                             baixo e deixava a outra metade vazia; aqui as duas
                             colunas terminam mais perto uma da outra. --}}
                        <ul class="cartao mt-8 divide-y divide-gray-100">
                            @foreach ($frente['itens'] as $item)
                                <li class="flex items-start gap-3 p-5 text-sm leading-relaxed text-gray-600">
                                    <svg class="mt-0.5 size-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- A foto entra recortada em 16:10 qualquer que seja o
                         original: as seis vem de fontes diferentes, e sem o
                         recorte cada bloco da pagina teria uma altura. --}}
                    @if ($frente['imagem'])
                        <figure class="overflow-hidden rounded-2xl border border-gray-200">
                            <img src="{{ asset('images/softwares/'.$frente['imagem']) }}"
                                 alt="{{ $frente['alt'] }}" width="1200" height="750" loading="lazy"
                                 class="aspect-[16/10] w-full object-cover">
                        </figure>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <x-site.chamada titulo="Não achou a sua rotina na lista?">
        Conte qual processo mais consome o tempo da sua equipe. Respondemos com uma proposta
        clara, com escopo e investimento definidos.
    </x-site.chamada>
@endsection
