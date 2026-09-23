@extends('layouts.site', [
    'titulo' => 'Softwares',
    'descricao' => 'Automação de processos, chat e atendimento humanizados, análise de mercado, automação de cobranças, integração de sistemas, controle de produção com CRM e desenvolvimento de sites, SaaS e web apps.',
])

@php
    // Cada frente em uma entrada: o titulo, o que ela resolve e o que entra no
    // escopo. A ancora e a mesma usada pelos cartoes da pagina inicial, entao
    // renomear uma frente aqui nao deixa link morto la.
    $frentes = [
        [
            'ancora' => 'rpa',
            'imagem' => 'rpa.jpg',
            'alt' => 'Código de automação projetado sobre a tela de um notebook enquanto alguém digita',
            'titulo' => 'Automação de processos',
            'texto' => 'Robôs que executam rotinas fiscais e financeiras e fazem sistemas diferentes trocarem informações, sem digitação.',
            'icone' => 'M9 3h6a2 2 0 0 1 2 2v1h1a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3h1V5a2 2 0 0 1 2-2ZM9 13h.01M15 13h.01M9.5 17h5',
            'itens' => [
                'Rotinas fiscais executadas sem intervenção manual',
                'Fluxos financeiros automatizados, com registro de cada etapa',
                'Integração entre sistemas que hoje não se comunicam',
            ],
        ],
        [
            'ancora' => 'mercado',
            'imagem' => 'mercado.jpg',
            'alt' => 'Painéis de indicadores e gráficos flutuando sobre um notebook e um tablet',
            'titulo' => 'Análise de mercado',
            'texto' => 'Robôs configurados para o seu negócio, que acompanham o mercado e mantêm as cotações de moedas e ativos sempre atualizadas.',
            'icone' => 'M3 17l5-5 4 4 8-9M16 7h5v5',
            'itens' => [
                'Monitoramento dos ativos e indicadores que importam para você',
                'Cotações de moedas e ativos atualizadas automaticamente',
                'Relatórios e alertas no formato que sua equipe já usa',
            ],
        ],
        [
            'ancora' => 'cobrancas',
            'imagem' => 'cobrancas.jpg',
            'alt' => 'Relatórios impressos com gráficos de barras, linhas e pizza sobre uma mesa',
            'titulo' => 'Automação de cobranças',
            'texto' => 'Da emissão do boleto à negativação, toda a jornada de cobrança roda de forma automática, no momento certo e no tom certo, com acompanhamento de cada pagamento em aberto.',
            'icone' => 'M12 3v18M16 7.5c0-1.4-1.8-2.5-4-2.5S8 6.1 8 7.5 9.8 10 12 10s4 1.1 4 2.5S14.2 15 12 15s-4-1.1-4-2.5',
            'itens' => [
                'Emissão e envio automático de boletos',
                'Alertas de vencimento e cobranças pelo WhatsApp e por e-mail',
                'Acompanhamento de cada pagamento, com baixa automática',
                'Negativação automática ao fim da régua, com a notificação prévia exigida por lei',
                'Régua de cobrança ajustada ao seu negócio',
            ],
        ],
        [
            'ancora' => 'ura',
            'imagem' => null,
            'titulo' => 'Chat e atendimento humanizados',
            'texto' => 'Da segmentação de leads ao atendimento ao cliente: chat inteligente, URA com voz natural gerada por IA e passagem para a sua equipe quando for preciso, integrados aos seus sistemas.',
            'icone' => 'M8 12h8M8 8.5h8M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 0 1 11 4h2a8 8 0 0 1 8 8Z',
            'itens' => [
                'Segmentação e qualificação de leads',
                'Conversas em linguagem natural, no tom da sua marca',
                'URA com respostas em áudio e voz natural gerada por IA',
                'Transferência para um atendente quando o cliente precisar',
                'Integração com a API do WhatsApp Business',
            ],
        ],
        [
            'ancora' => 'gestao',
            'imagem' => 'gestao.jpg',
            'alt' => 'Equipe reunida diante de monitores acompanhando um painel de operação',
            'titulo' => 'Controle de produção e CRM',
            'texto' => 'Acompanhamento da produção e um CRM enxuto, desenhados para o jeito que sua empresa trabalha.',
            'icone' => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
            'itens' => [
                'Acompanhamento de cada etapa da produção',
                'CRM enxuto, apenas com o que sua equipe usa',
                'Fluxos desenhados a partir do processo da sua empresa',
            ],
        ],
        [
            'ancora' => 'integracao',
            'imagem' => 'integracao.jpg',
            'alt' => 'Duas pessoas revisando código lado a lado em monitores de um escritório',
            'titulo' => 'Integração de sistemas',
            'texto' => 'Analisamos sua operação de perto, fazemos o levantamento das necessidades e desenvolvemos integrações que resolvem gargalos e centralizam os dados, sem substituir os sistemas que você já usa.',
            'icone' => 'M9 12h6M7 7h10a4 4 0 0 1 0 8h-1M17 17H7a4 4 0 0 1 0-8h1',
            'itens' => [
                'Levantamento das necessidades junto à sua equipe',
                'Integrações entre ERP, bancos, APIs e WhatsApp',
                'Dados centralizados em uma fonte única e confiável',
                'Painéis claros para acompanhar a operação',
            ],
        ],
        [
            'ancora' => 'desenvolvimento',
            'imagem' => 'desenvolvimento.jpg',
            'alt' => 'Desenvolvedor diante da bancada com notebook e dois monitores mostrando código',
            'titulo' => 'Sites, SaaS e web apps',
            'texto' => 'Produtos digitais sob medida, do site institucional à plataforma SaaS, com a mesma engenharia das nossas automações.',
            'icone' => 'm9 8-5 4 5 4M15 8l5 4-5 4',
            'itens' => [
                'Sites institucionais e páginas de venda',
                'Plataformas SaaS e web apps sob medida',
                'Integração com os sistemas e automações que você já usa',
            ],
        ],
    ];
@endphp

@section('content')
    <x-site.cabecalho selo="Soluções" icone="M4 6.5h6v6H4zM14 6.5h6v6h-6zM4 16h6v4H4zM14 16h6v4h-6z" titulo="Softwares para cada rotina da sua operação">
        Todo projeto começa pelo seu processo. Escolha uma frente ou combine várias: todas se
        integram entre si e aos sistemas que você já usa.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] space-y-16 px-6 lg:space-y-24">
            @foreach ($frentes as $frente)
                {{-- As colunas se alternam a cada frente. Sete blocos iguais
                     empilhados viram uma coluna so aos olhos de quem rola; o
                     zigue-zague marca onde um assunto termina e o outro
                     comeca, sem precisar de mais uma linha divisoria. --}}
                <article id="{{ $frente['ancora'] }}" class="grid scroll-mt-20 items-start gap-8 lg:grid-cols-2 lg:gap-14">
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
                    </div>

                    <div class="space-y-5">
                        {{-- A foto entra recortada em 16:10 qualquer que seja o
                             original: as sete vem de fontes diferentes, e sem o
                             recorte cada bloco da pagina teria uma altura. --}}
                        @if ($frente['imagem'])
                            <figure class="overflow-hidden rounded-2xl border border-gray-200">
                                <img src="{{ asset('images/softwares/'.$frente['imagem']) }}"
                                     alt="{{ $frente['alt'] }}" width="1200" height="750" loading="lazy"
                                     class="aspect-[16/10] w-full object-cover">
                            </figure>
                        @endif

                        <ul class="cartao divide-y divide-gray-100">
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
                </article>
            @endforeach
        </div>
    </section>

    <x-site.chamada titulo="Não achou a sua rotina na lista?">
        Conte qual processo mais consome o tempo da sua equipe. Respondemos com uma proposta
        clara, com escopo e investimento definidos.
    </x-site.chamada>
@endsection
