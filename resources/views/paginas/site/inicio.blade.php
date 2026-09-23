@extends('layouts.site', [
    'titulo' => 'Automação e software sob medida',
    'descricao' => 'Software sob medida que conecta sistemas e pessoas: automação de processos, atendimento humanizado com IA, cobrança, análise de mercado e integração de sistemas.',
])

@php
    use App\Support\Empresa;

    // A vitrine de softwares. Titulo, texto e ancora juntos, porque o cartao
    // da home e a secao da pagina de softwares precisam dizer a mesma coisa:
    // separados, o cartao prometia um nome e a secao entregava outro.
    $softwares = [
        [
            'ancora' => 'rpa',
            'titulo' => 'Automação de processos',
            'texto' => 'Robôs que executam rotinas fiscais e financeiras e fazem sistemas diferentes trocarem informações, eliminando os erros comuns do trabalho manual.',
            'icone' => 'M9 3h6a2 2 0 0 1 2 2v1h1a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3h1V5a2 2 0 0 1 2-2ZM9 13h.01M15 13h.01M9.5 17h5',
        ],
        [
            'ancora' => 'mercado',
            'titulo' => 'Análise de mercado',
            'texto' => 'Monitoramento contínuo do mercado e cotações de ativos atualizadas, no formato que sua equipe já usa.',
            'icone' => 'M3 17l5-5 4 4 8-9M16 7h5v5',
        ],
        [
            'ancora' => 'cobrancas',
            'titulo' => 'Automação de cobranças',
            'texto' => 'Gestão de alertas e cobranças, enviados no momento certo, com acompanhamento de cada pagamento em aberto.',
            'icone' => 'M12 3v18M16 7.5c0-1.4-1.8-2.5-4-2.5S8 6.1 8 7.5 9.8 10 12 10s4 1.1 4 2.5S14.2 15 12 15s-4-1.1-4-2.5',
        ],
        [
            'ancora' => 'ura',
            'titulo' => 'Chat e atendimento humanizados',
            'texto' => 'Segmentação de leads, atendimento ao cliente, chat inteligente e URA com voz natural gerada por IA, integrados aos seus sistemas.',
            'icone' => 'M8 12h8M8 8.5h8M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 0 1 11 4h2a8 8 0 0 1 8 8Z',
        ],
        [
            'ancora' => 'gestao',
            'titulo' => 'Controle de produção e CRM',
            'texto' => 'Acompanhamento da produção e um CRM enxuto, desenhados para o jeito que sua empresa trabalha.',
            'icone' => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
        ],
        [
            'ancora' => 'desenvolvimento',
            'titulo' => 'Sites, SaaS e web apps',
            'texto' => 'Produtos digitais sob medida, do site institucional à plataforma SaaS, com a mesma engenharia das nossas automações.',
            'icone' => 'm9 8-5 4 5 4M15 8l5 4-5 4',
        ],
    ];

    // Os termos que correm no pe do herói. A lista sai duplicada no trilho,
    // entao basta escrevê-la uma vez.
    $termos = [
        'Atendimento humanizado', 'Automação de processos', 'Análise de mercado', 'Cobranças',
        'Integração de sistemas', 'Controle de produção', 'CRM', 'Sites e SaaS',
    ];
@endphp

@section('content')
    {{-- Herói. A grade escura da marca ao fundo, a promessa na frente. --}}
    <section class="superficie-escura grade-viva-escura relative overflow-hidden">
        <div class="mx-auto grid w-full max-w-[87rem] items-center gap-12 px-6 py-20 lg:grid-cols-2 lg:py-28">
            <div class="entra-suave">
                <span class="selo selo-claro">Software · Automação · Integração</span>

                <h1 class="mt-6 text-title-sm font-semibold tracking-tight sm:text-title-md lg:text-title-lg">
                    Sua empresa em <span class="texto-bureau">movimento.</span><br>
                    Sem trabalho manual.
                </h1>

                <p class="mt-5 max-w-lg text-lg leading-relaxed text-white/60">
                    Criamos software sob medida que integra sua equipe, seus sistemas e seus dados.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <x-avalia.botao :href="route('site.contato')">
                        Mapear minha operação
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </x-avalia.botao>

                    <a href="#aplicacoes" class="botao border border-white/20 text-white transition hover:bg-white/10">
                        Ver as aplicações
                    </a>
                </div>
            </div>

            {{-- O diagrama do fluxo: entrada, motor e saída. Decorativo, entao
                 fica fora da arvore de acessibilidade: quem usa leitor de tela
                 ja recebeu a mesma ideia no texto ao lado. --}}
            <div class="flutua rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur" aria-hidden="true">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <span class="flex items-center gap-2 text-sm text-white/70">
                        <i class="size-2 rounded-full bg-success-400"></i>{{ strtolower(Empresa::marca()) }}.fluxo
                    </span>
                    <code class="etiqueta bg-success-500/15 text-success-400">EXECUTANDO</code>
                </div>

                <div class="grid grid-cols-3 gap-3 py-8">
                    @foreach ([['ENTRADA', 'ERP'], ['MOTOR', Empresa::marca()], ['SAÍDA', 'Banco']] as [$papel, $nome])
                        <div class="rounded-xl border border-white/10 bg-gray-900/60 p-3 text-center">
                            <small class="block text-[10px] tracking-[0.18em] text-white/40">{{ $papel }}</small>
                            <strong class="mt-1 block text-sm text-white">{{ $nome }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="relative h-px bg-white/10">
                    <i class="pulso absolute -top-[3px] size-1.5 rounded-full bg-brand-400" style="--percurso: 100%"></i>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-white/60">Conciliando lançamentos</span>
                        <strong class="text-white">84%</strong>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <i class="barra-cresce barra-bureau block h-full w-[84%] rounded-full"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Trilho de termos. Dois grupos identicos: o segundo cobre a saida
             do primeiro, entao o laco nao tem emenda visivel. --}}
        <div class="overflow-hidden border-t border-white/10 py-4" aria-hidden="true">
            <div class="trilho flex w-max items-center gap-8 text-sm whitespace-nowrap text-white/40">
                @foreach (array_merge($termos, $termos, $termos, $termos) as $termo)
                    <span class="flex items-center gap-8">{{ $termo }}<i class="size-1 rounded-full bg-brand-500"></i></span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- As aplicações da casa.

         A holding opera negócios próprios e vende software sob demanda, e a
         porta de cada negócio precisa aparecer aqui: quem chega pelo domínio
         procurando o sistema que já usa não devia ter que adivinhar por onde
         se entra. --}}
    <section id="aplicacoes" class="scroll-mt-[60px] bg-gray-50 py-20 lg:py-24">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="max-w-3xl">
                <span class="indice">01 / Aplicações</span>
                <h2 class="mt-3 text-title-sm font-semibold tracking-tight text-gray-900">
                    Negócios próprios, <span class="texto-bureau">no ar</span> e em operação.
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-gray-600">
                    Além do software sob medida, a {{ Empresa::marca() }} mantém as próprias plataformas.
                    Quem já é cliente entra por aqui.
                </p>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-2">
                <a href="{{ route('credito') }}" class="bloco group">
                    <div class="flex items-center justify-between">
                        <x-avalia.logotipo :tamanho="34" texto="1.2rem" marca="credito" />
                        <span class="etiqueta etiqueta-sucesso">Em operação</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Pesquisa de score para empresas</h3>
                    <p class="text-gray-600">
                        Pesquise o score e os dados públicos antes de fechar a venda a prazo.
                        O resultado chega em segundos, direto no painel da sua equipe.
                    </p>
                    <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                        Conhecer o {{ Empresa::marcaCredito() }}
                        <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </span>
                </a>

                <a href="{{ route('cobranca') }}" class="bloco group">
                    <div class="flex items-center justify-between">
                        <x-avalia.logotipo :tamanho="34" texto="1.2rem" marca="cobranca" />
                        <span class="etiqueta etiqueta-sucesso">Em operação</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Venda parcelada, cobrança e APIs</h3>
                    <p class="text-gray-600">
                        Parcele a venda em boleto e Pix sem depender do cartão do cliente, com a
                        régua de cobrança e o repasse acontecendo sozinhos.
                    </p>
                    <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                        Conhecer o {{ Empresa::marcaCobranca() }}
                        <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </span>
                </a>
            </div>

            <a href="{{ route('area') }}" class="mt-6 flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md">
                <span class="icone-caixa">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a1 1 0 011 1v16a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3" />
                    </svg>
                </span>
                <span class="text-sm text-gray-600">
                    <strong class="font-semibold text-gray-900">Já tem conta em uma das plataformas?</strong>
                    A área do produtor reúne as duas entradas num lugar só.
                </span>
                <svg class="ml-auto size-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </a>
        </div>
    </section>

    {{-- A vitrine de software sob demanda. --}}
    <section class="py-20 lg:py-24">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="indice">02 / Softwares</span>
                    <h2 class="mt-3 text-title-sm font-semibold tracking-tight text-gray-900">
                        Mais produtividade à equipe, execução com <span class="texto-bureau">controle</span> e <span class="texto-bureau">precisão</span>.
                    </h2>
                </div>
                <p class="max-w-md text-gray-600">
                    Software personalizado para cada necessidade. Conectadas, as soluções compartilham
                    dados, aumentam a produtividade e ganham escala.
                </p>
            </div>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($softwares as $indice => $software)
                    <a href="{{ route('site.softwares') }}#{{ $software['ancora'] }}" class="bloco group">
                        <div class="flex items-start justify-between">
                            <span class="icone-caixa">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $software['icone'] }}" />
                                </svg>
                            </span>
                            <code class="text-xs font-medium text-gray-300">{{ str_pad($indice + 1, 2, '0', STR_PAD_LEFT) }}</code>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $software['titulo'] }}</h3>
                        <p class="text-sm leading-relaxed text-gray-600">{{ $software['texto'] }}</p>
                        <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                            Saiba mais
                            <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>

            <a href="{{ route('site.softwares') }}#integracao"
               class="mt-5 flex items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 transition hover:border-brand-300 hover:bg-white hover:shadow-theme-md">
                <span class="icone-caixa">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M7 7h10a4 4 0 0 1 0 8h-1M17 17H7a4 4 0 0 1 0-8h1" />
                    </svg>
                </span>
                <span class="text-sm text-gray-600">
                    <strong class="font-semibold text-gray-900">Conecte o que sua empresa já usa.</strong>
                    Integramos ERP, bancos e APIs em uma única camada, sem substituir nenhum sistema.
                </span>
                <svg class="ml-auto size-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </a>
        </div>
    </section>

    {{-- Como funciona: do evento à decisão. --}}
    <section class="superficie-escura grade-viva-escura">
        <div class="mx-auto grid w-full max-w-[87rem] gap-12 px-6 py-20 lg:grid-cols-2 lg:py-24">
            <div>
                <span class="indice indice-claro">03 / Como funciona</span>
                <h2 class="mt-3 text-title-sm font-semibold tracking-tight">
                    Dados centralizados<br>em um painel limpo.
                </h2>
                <p class="mt-4 max-w-md leading-relaxed text-white/60">
                    A {{ Empresa::marca() }} cuida do caminho entre o evento e a decisão: recebe os dados,
                    aplica as regras e só aciona sua equipe quando é necessário.
                </p>

                <ol class="mt-8 space-y-3">
                    @foreach ([
                        ['Sistemas conectados', 'Fluxo de dados com integração segura entre múltiplas plataformas.'],
                        ['Visão inteligente', 'Validações, respostas e rotinas acontecem automaticamente, seguindo as regras do seu negócio.'],
                        ['Equipe no controle', 'As exceções chegam a quem decide, com a informação pronta para a tomada de decisão.'],
                    ] as $passo => $etapa)
                        <li class="flex items-start gap-4 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                            <span class="text-sm font-semibold text-brand-300">{{ str_pad($passo + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <div>
                                <h3 class="font-semibold text-white">{{ $etapa[0] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-white/60">{{ $etapa[1] }}</p>
                            </div>
                            <span class="etiqueta ml-auto shrink-0 bg-success-500/15 text-success-400">Ativo</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="self-center rounded-2xl border border-white/10 bg-gray-950/60 p-5 font-mono text-sm" aria-hidden="true">
                <div class="flex items-center justify-between border-b border-white/10 pb-3 text-xs text-white/40">
                    <span>automacao.yaml</span>
                    <span>● ● ●</span>
                </div>
                <pre class="mt-4 leading-loose text-white/70"><code><span class="text-brand-300">gatilho:</span> nota_fiscal.recebida
<span class="text-brand-300">validar:</span> regras_fiscais.br
<span class="text-brand-300">conciliar:</span> extrato.bancario
<span class="text-brand-300">notificar:</span> equipe.financeiro
<b class="text-success-400">status: concluido</b></code></pre>
            </div>
        </div>
    </section>

    {{-- Quem somos, resumido. O texto inteiro mora na própria página. --}}
    <section class="py-20 lg:py-24">
        <div class="mx-auto grid w-full max-w-[87rem] gap-10 px-6 lg:grid-cols-[auto_1fr] lg:gap-20">
            <span class="indice">04 / Quem somos</span>
            <div class="max-w-2xl">
                <h2 class="text-title-sm font-semibold tracking-tight text-gray-900">
                    Tecnologia feita para a <span class="texto-bureau">rotina real</span> da sua empresa.
                </h2>
                <div class="mt-5 space-y-4 text-lg leading-relaxed text-gray-600">
                    <p>
                        Somos uma software house de produtos digitais. Criamos automações, atendimentos
                        humanizados e sistemas sob medida para operações fiscais, financeiras, comerciais
                        e de marketing.
                    </p>
                    <p>
                        Unimos engenharia, automação e conhecimento de negócio para entregar resultados
                        que aparecem no dia a dia da sua equipe.
                    </p>
                </div>
                <a href="{{ route('site.quem-somos') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700">
                    Conheça a {{ Empresa::marca() }}
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Próximo passo. --}}
    <section class="pb-20 lg:pb-24">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="superficie-escura grade-viva-escura flex flex-col items-start gap-6 overflow-hidden rounded-2xl p-8 lg:flex-row lg:items-center lg:justify-between lg:p-12">
                <div class="max-w-xl">
                    <span class="indice indice-claro">Próximo passo</span>
                    <h2 class="mt-3 text-title-sm font-semibold tracking-tight">Vamos tirar uma rotina do caminho?</h2>
                    <p class="mt-3 leading-relaxed text-white/60">
                        Conte qual processo mais consome o tempo da sua equipe. Respondemos com uma
                        proposta clara, com escopo e investimento definidos.
                    </p>
                </div>
                <x-avalia.botao :href="route('site.contato')" class="shrink-0">
                    Solicitar proposta
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </x-avalia.botao>
            </div>
        </div>
    </section>
@endsection
