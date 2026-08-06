@extends('layouts.fullscreen-layout', ['title' => 'Consultas de crédito para empresas'])

@php
    use App\Support\Suporte;

    // Celulas que acendem na grade do topo. Posicoes multiplas de 42px, o passo
    // da grade, para a celula cair exatamente dentro de um quadradinho.
    $celulas = [
        ['top' => 1, 'left' => 2, 'atraso' => '0s'],
        ['top' => 3, 'left' => 5, 'atraso' => '0.9s'],
        ['top' => 6, 'left' => 1, 'atraso' => '1.7s'],
        ['top' => 2, 'left' => 9, 'atraso' => '2.4s'],
        ['top' => 8, 'left' => 7, 'atraso' => '3.2s'],
        ['top' => 5, 'left' => 12, 'atraso' => '3.9s'],
        ['top' => 9, 'left' => 3, 'atraso' => '4.6s'],
        ['top' => 4, 'left' => 15, 'atraso' => '1.3s'],
    ];
@endphp

@section('content')
    <div class="min-h-screen bg-white text-gray-800 dark:bg-gray-900 dark:text-white/90">

        {{-- Topo fixo: a marca e as duas unicas saidas, entrar ou conversar. --}}
        <header class="fixed inset-x-0 top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur dark:border-gray-800 dark:bg-gray-900/80">
            <div class="mx-auto flex h-[76px] w-full max-w-6xl items-center justify-between px-6">
                <x-avalia.logotipo :tamanho="32" />

                <nav class="flex items-center gap-3">
                    <x-avalia.botao variante="secundario" tamanho="sm" :href="route('entrar')">Entrar</x-avalia.botao>
                    <a href="{{ Suporte::whatsapp('Quero conhecer a Avalia') }}" target="_blank" rel="noopener noreferrer"
                       class="botao botao-primario botao-sm hidden sm:inline-flex">
                        Falar com um consultor
                    </a>
                </nav>
            </div>
        </header>

        {{-- Herói: a grade viva da marca ao fundo, a promessa na frente. --}}
        <section class="grade-viva relative overflow-hidden pt-[76px]">
            <div aria-hidden="true" class="absolute inset-0">
                @foreach ($celulas as $celula)
                    <span class="celula-viva"
                          style="top: {{ $celula['top'] * 42 }}px; left: {{ $celula['left'] * 42 }}px; animation-delay: {{ $celula['atraso'] }}"></span>
                @endforeach
            </div>
            <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent to-white dark:to-gray-900"></div>

            <div class="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-6 py-20 lg:grid-cols-2 lg:py-28">
                <div>
                    <span class="entra-suave inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
                        <span class="size-1.5 rounded-full bg-brand-500"></span>
                        Consultas de crédito para empresas
                    </span>

                    <h1 class="entra-suave mt-5 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl"
                        style="animation-delay: 0.1s">
                        Venda a prazo com a<br>
                        <span class="text-brand-500">decisão certa.</span>
                    </h1>

                    <p class="entra-suave mt-5 max-w-lg text-lg text-gray-500 dark:text-gray-400"
                       style="animation-delay: 0.2s">
                        Crédito, cadastro e veicular em um painel só. Sua empresa consulta
                        antes de vender, acompanha o consumo e recebe uma fatura única por mês.
                    </p>

                    <div class="entra-suave mt-8 flex flex-wrap items-center gap-3" style="animation-delay: 0.3s">
                        <a href="{{ Suporte::whatsapp('Quero contratar a Avalia') }}" target="_blank" rel="noopener noreferrer"
                           class="botao botao-primario">
                            Quero contratar
                        </a>
                        <x-avalia.botao variante="secundario" :href="route('entrar')">Já sou cliente</x-avalia.botao>
                    </div>

                    <ul class="entra-suave mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500 dark:text-gray-400"
                        style="animation-delay: 0.4s">
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            Preço por consulta
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            Atendimento humano
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            LGPD desde o desenho
                        </li>
                    </ul>
                </div>

                {{-- Uma sessao de consultas acontecendo: o medidor refaz a
                     leitura a cada cliente do carrossel, e a pontuacao sai na
                     cor de bureau, do rosa ao azul.

                     Todo nome e ficticio e todo documento e mascarado, e o
                     cartao diz "Simulação" de proposito: uma vitrine publica
                     exibindo consulta que parecesse real seria exatamente o
                     vazamento que o produto promete impedir. --}}
                <div class="entra-suave relative mx-auto w-full max-w-md" style="animation-delay: 0.25s">
                    <div class="cartao relative flex flex-col items-center px-8 pt-10 pb-8">
                        <span class="absolute top-4 right-4 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-400 dark:bg-white/5 dark:text-gray-500">
                            Simulação
                        </span>

                        <x-avalia.medidor :tamanho="150" vivo />

                        <div class="mt-6 h-px w-full bg-gray-100 dark:bg-gray-800"></div>

                        <div class="relative mt-5 h-[92px] w-full" aria-hidden="true">
                            @foreach ([
                                ['tipo' => 'CNPJ', 'nome' => 'Casa Sul Materiais', 'doc' => '12.345.678/0001-**', 'pontos' => 782, 'faixa' => 78],
                                ['tipo' => 'CPF', 'nome' => 'Marcelo Silveira', 'doc' => '***.482.916-**', 'pontos' => 645, 'faixa' => 64],
                                ['tipo' => 'CNPJ', 'nome' => 'Reparo Tecnologia', 'doc' => '98.765.432/0001-**', 'pontos' => 823, 'faixa' => 82],
                                ['tipo' => 'CPF', 'nome' => 'Helena Duarte', 'doc' => '***.157.204-**', 'pontos' => 597, 'faixa' => 60],
                            ] as $i => $consulta)
                                <div class="consulta-do-carrossel flex items-center justify-between gap-4"
                                     style="animation-delay: {{ $i * 4 }}s">
                                    <div class="min-w-0">
                                        <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $consulta['tipo'] === 'CPF' ? 'bg-theme-pink-500/10 text-theme-pink-500' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' }}">
                                            {{ $consulta['tipo'] }}
                                        </span>
                                        <p class="mt-1.5 truncate font-medium text-gray-800 dark:text-white/90">{{ $consulta['nome'] }}</p>
                                        <p class="text-xs text-gray-400 tabular-nums dark:text-gray-500">{{ $consulta['doc'] }}</p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <span class="numero-bureau text-4xl font-semibold tabular-nums">{{ $consulta['pontos'] }}</span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">pontos</span>
                                        <div class="mt-1.5 ml-auto h-1 w-20 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="barra-bureau h-full rounded-full" style="width: {{ $consulta['faixa'] }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="cartao flutua absolute -top-5 -right-3 flex items-center gap-2.5 px-4 py-3 shadow-theme-md sm:-right-8">
                        <span class="etiqueta etiqueta-sucesso">Concluída</span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Consulta de CNPJ</span>
                    </div>

                    <div class="cartao flutua absolute -bottom-5 -left-3 flex items-center gap-2.5 px-4 py-3 shadow-theme-md sm:-left-8"
                         style="animation-delay: 1.4s">
                        <span class="etiqueta etiqueta-neutra">Registrada</span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Finalidade declarada</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- O que a empresa leva. Tres pilares, sem tabela de preco: preco tem
             dono, contrato e catalogo vigente, e mora atras do login. --}}
        <section class="mx-auto w-full max-w-6xl px-6 py-16 lg:py-20">
            <div class="grid gap-6 md:grid-cols-3">
                <div class="cartao p-7">
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l5-5 4 4 8-9M16 7h5v5"/>
                        </svg>
                    </div>
                    <h2 class="font-semibold">Decida com dados</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Consulte crédito e cadastro antes de fechar a venda a prazo.
                        O resultado chega em segundos, direto no painel.
                    </p>
                </div>

                <div class="cartao p-7">
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.6-7 10-4-1.4-7-5.5-7-10V6l7-3zM9.5 12l1.8 1.8 3.2-3.6"/>
                        </svg>
                    </div>
                    <h2 class="font-semibold">Evite a fraude</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Confirme quem está do outro lado do negócio, do CPF ao veículo,
                        antes de o prejuízo entrar pela porta.
                    </p>
                </div>

                <div class="cartao p-7">
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h9a2 2 0 012 2v9a2 2 0 01-2 2H9a2 2 0 01-2-2V9a2 2 0 012-2zM7 15H6a2 2 0 01-2-2V5a2 2 0 012-2h8a2 2 0 012 2v1M11 12h5M11 15.5h5"/>
                        </svg>
                    </div>
                    <h2 class="font-semibold">Pague pelo que usar</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Cada consulta tem preço definido em contrato, e o mês fecha em uma
                        fatura única. Consumo e franquia à vista no painel, sem surpresa.
                    </p>
                </div>
            </div>
        </section>

        {{-- Campanha de adesao: o convite direto, no fundo escuro da marca. --}}
        <section class="mx-auto w-full max-w-6xl px-6 pb-16 lg:pb-24">
            <div class="grade-viva-escura relative overflow-hidden rounded-3xl bg-brand-950 px-8 py-14 text-center sm:px-14">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white">
                    <span class="size-1.5 animate-pulse rounded-full bg-success-500"></span>
                    Campanha de adesão aberta
                </span>

                <h2 class="mx-auto mt-5 max-w-2xl text-3xl font-semibold text-white sm:text-4xl">
                    Comece a consultar ainda esta semana
                </h2>

                <p class="mx-auto mt-4 max-w-xl text-gray-400">
                    Novos clientes contam com condições especiais de adesão e uma
                    proposta sob medida para o volume da sua empresa. A conversa começa
                    no WhatsApp e a proposta chega no mesmo dia.
                </p>

                <a href="{{ Suporte::whatsapp('Campanha de adesão') }}" target="_blank" rel="noopener noreferrer"
                   class="mt-8 inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-medium text-brand-950 transition hover:bg-brand-50">
                    Quero aproveitar a campanha
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M18 12H6"/>
                    </svg>
                </a>
            </div>
        </section>

        <footer class="border-t border-gray-100 dark:border-gray-800">
            <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-gray-500 dark:text-gray-400">
                <x-avalia.logotipo :tamanho="24" />
                <p>© {{ now()->year }} Avalia. Consultas com finalidade declarada e trilha de auditoria.</p>
                <div class="flex items-center gap-5">
                    <a class="hover:text-brand-500" href="{{ route('entrar') }}">Entrar</a>
                    <a class="hover:text-brand-500" href="{{ Suporte::whatsapp() }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                </div>
            </div>
        </footer>
    </div>
@endsection
