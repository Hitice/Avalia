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

    // Os tres estagios do diagrama do herói, e o instante em que cada um
    // acende dentro do ciclo de 6s. O conector parte meio segundo depois do no
    // que o alimenta e leva 2s para atravessar, entao o vizinho acende
    // exatamente quando o pulso chega nele: e esse encaixe que faz o desenho
    // parecer um caminho, e nao tres luzes piscando fora de hora.
    //
    // `parado` marca o estagio que fica aceso para quem pediu menos movimento.
    // O motor, e nao a entrada: parado no primeiro no, o desenho nao diz o que
    // o fluxo faz.
    $fluxo = [
        ['papel' => 'ENTRADA', 'nome' => 'ERP', 'legenda' => 'Lendo as notas do ERP',
            'atraso' => '0s', 'saida' => '0.55s', 'parado' => false],
        ['papel' => 'MOTOR', 'nome' => Empresa::marca(), 'legenda' => 'Aplicando as regras do negócio',
            'atraso' => '2s', 'saida' => '2.55s', 'parado' => true],
        ['papel' => 'SAÍDA', 'nome' => 'Banco', 'legenda' => 'Conciliando lançamentos',
            'atraso' => '4s', 'saida' => null, 'parado' => false],
    ];

    // As tres operacoes que o terminal roda em rodizio, uma por vez, num ciclo
    // de 24s. Uma so, repetindo para sempre, dizia que a casa faz uma coisa; o
    // rodizio mostra tres frentes diferentes trabalhando, que e o que a secao
    // ao lado promete.
    //
    // O atraso de cada bloco e o terco dele no ciclo. As linhas de dentro se
    // escrevem sempre nos mesmos instantes, meio segundo entre elas: menos que
    // isso as quatro saem juntas e nao parece digitacao, mais que isso cansa.
    $tempos = ['0s', '0.5s', '1s', '1.5s'];

    $operacoes = [
        [
            'arquivo' => 'fiscal.yaml',
            'atraso' => '0s',
            // Fica visivel para quem pediu menos movimento: e a operacao que o
            // texto ao lado descreve.
            'parada' => true,
            'linhas' => [
                ['gatilho:', 'nota_fiscal.recebida'],
                ['validar:', 'regras_fiscais.br'],
                ['conciliar:', 'extrato.bancario'],
                ['notificar:', 'equipe.financeiro'],
            ],
        ],
        [
            'arquivo' => 'cobranca.yaml',
            'atraso' => '8s',
            'parada' => false,
            'linhas' => [
                ['gatilho:', 'parcela.venceu'],
                ['consultar:', 'titulos_em_aberto'],
                ['enviar:', 'lembrete.whatsapp'],
                ['baixar:', 'extrato.conciliado'],
            ],
        ],
        [
            'arquivo' => 'atendimento.yaml',
            'atraso' => '16s',
            'parada' => false,
            'linhas' => [
                ['gatilho:', 'mensagem.recebida'],
                ['classificar:', 'intencao_do_cliente'],
                ['responder:', 'ura.voz_natural'],
                ['transferir:', 'fila.atendente'],
            ],
        ],
    ];

@endphp

@section('content')
    {{-- Herói. A grade escura da marca ao fundo, a promessa na frente. --}}
    <section class="superficie-escura relative overflow-hidden">
        {{-- A faixa do herói mede oito quadradinhos de altura: 8 x 42px, o
             passo da grade. Altura fixa, e nao folga vertical, porque o que se
             quer e exatamente isto: a grade fechando em oito linhas inteiras e
             a secao seguinte comecando a aparecer sem ninguem precisar rolar.
             No celular a altura volta a ser a do conteudo, que empilha. --}}
        <div class="grade-viva-escura lg:h-[336px]">
            <div class="mx-auto grid h-full w-full max-w-[87rem] items-center gap-8 px-6 py-12 lg:grid-cols-2 lg:py-0">
                <div class="entra-suave">
                    <span class="selo selo-claro">Software · Automação · Integração</span>

                    <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-title-sm lg:text-title-md">
                        Sua empresa em <span class="texto-bureau">movimento.</span><br>
                        Sem trabalho manual.
                    </h1>

                    <p class="mt-4 max-w-md leading-relaxed text-white/60">
                        Criamos software sob medida que integra sua equipe, seus sistemas e seus dados.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
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
                <div class="flutua rounded-2xl border border-white/10 bg-white/[0.04] p-4 backdrop-blur" aria-hidden="true">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <span class="flex items-center gap-2 text-sm text-white/70">
                            <i class="size-2 rounded-full bg-success-400"></i>{{ strtolower(Empresa::marca()) }}.fluxo
                        </span>
                        <code class="etiqueta bg-success-500/15 text-success-400">EXECUTANDO</code>
                    </div>

                    <div class="flex items-center py-6">
                        @foreach ($fluxo as $estagio)
                            <div class="relative min-w-0 flex-1 rounded-xl border border-white/10 bg-gray-900/60 p-2.5 text-center sm:p-3">
                                {{-- O brilho da vez, por cima do cartao. --}}
                                <i class="acende-no {{ $estagio['parado'] ? 'acende-no-parado' : '' }} absolute inset-0 rounded-xl bg-brand-500/15 ring-1 ring-brand-400/50"
                                   style="--atraso: {{ $estagio['atraso'] }}"></i>

                                <span class="relative block">
                                    <small class="block text-[10px] tracking-[0.18em] text-white/40">{{ $estagio['papel'] }}</small>
                                    <strong class="mt-1 block text-sm text-white">{{ $estagio['nome'] }}</strong>
                                </span>
                            </div>

                            @if ($estagio['saida'])
                                {{-- O trecho entre dois nos: a linha apagada, o rastro que o
                                     pulso deixa e o pulso em si. --}}
                                <div class="relative h-px w-10 shrink-0 bg-white/10 sm:w-14">
                                    <i class="risca-fluxo absolute inset-0 bg-brand-400/70" style="--atraso: {{ $estagio['saida'] }}"></i>
                                    <i class="corre-fluxo absolute top-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-success-400 shadow-[0_0_10px_2px_rgb(50_213_131/0.6)]"
                                       style="--atraso: {{ $estagio['saida'] }}"></i>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            {{-- As tres legendas dividem a mesma linha, empilhadas, e cada
                                 uma aparece na vez do seu no. Altura fixa: sem ela, a troca
                                 de frase mexeria na altura do painel inteiro. --}}
                            <span class="relative block h-5 min-w-0 flex-1">
                                @foreach ($fluxo as $estagio)
                                    <span class="troca-legenda {{ $estagio['parado'] ? 'troca-legenda-parada' : '' }} absolute inset-0 truncate text-white/60"
                                          style="--atraso: {{ $estagio['atraso'] }}">{{ $estagio['legenda'] }}</span>
                                @endforeach
                            </span>
                            <strong class="shrink-0 text-white">84%</strong>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <i class="barra-cresce barra-bureau block h-full w-[84%] rounded-full"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-site.trilho />
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
                    Conheça nossas plataformas em destaque.
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
                    <strong class="font-semibold text-gray-900">Já é cliente {{ Empresa::marca() }}?</strong>
                    Acesse aqui.
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

    {{-- Como funciona: do evento à decisão.

         O cabecalho ocupa a largura inteira e as duas colunas comecam juntas
         abaixo dele. Antes o titulo morava dentro da coluna da esquerda e o
         terminal ficava centralizado na altura da secao: ele nascia no meio do
         nada, sem alinhar com passo nenhum da lista ao lado. --}}
    <section class="superficie-escura grade-viva-escura">
        <div class="mx-auto w-full max-w-[87rem] px-6 py-20 lg:py-24">
            <div class="max-w-2xl">
                <span class="indice indice-claro">03 / Como funciona</span>
                <h2 class="mt-3 text-title-sm font-semibold tracking-tight">
                    Dados centralizados em um painel limpo.
                </h2>
                <p class="mt-4 leading-relaxed text-white/60">
                    A {{ Empresa::marca() }} cuida do caminho entre o evento e a decisão: recebe os dados,
                    aplica as regras e só aciona sua equipe quando é necessário.
                </p>
            </div>

            <div class="mt-10 grid items-start gap-8 lg:grid-cols-2 lg:gap-12">
                <ol class="space-y-3">
                    @foreach ([
                        ['Sistemas conectados', 'Fluxo de dados com integração segura entre múltiplas plataformas.'],
                        ['Visão inteligente', 'Validações, respostas e rotinas acontecem automaticamente, seguindo as regras do seu negócio.'],
                        ['Equipe no controle', 'As exceções chegam a quem decide, com a informação pronta para a tomada de decisão.'],
                    ] as $passo => $etapa)
                        {{-- O contorno acende ao passar o mouse. Os tres cartoes
                             sao blocos parados num fundo escuro, e sem resposta
                             ao cursor a secao inteira parece uma imagem: o
                             realce diz que ha algo vivo ali, mesmo que o cartao
                             nao leve a lugar nenhum. --}}
                        <li class="group flex items-start gap-4 rounded-xl border border-white/10 bg-white/[0.03] p-4 transition duration-200 hover:border-brand-400/60 hover:bg-white/[0.07]">
                            <span class="text-sm font-semibold text-brand-300 transition group-hover:text-brand-200">{{ str_pad($passo + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <div>
                                <h3 class="font-semibold text-white">{{ $etapa[0] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-white/60">{{ $etapa[1] }}</p>
                            </div>
                            <span class="etiqueta ml-auto shrink-0 bg-success-500/15 text-success-400">Ativo</span>
                        </li>
                    @endforeach
                </ol>

                {{-- O terminal, que se escreve sozinho e fecha em concluido.
                     Decorativo: o que ele diz ja esta nos tres passos ao lado. --}}
                <div class="rounded-2xl border border-white/10 bg-gray-950/60 p-5 font-mono text-sm" aria-hidden="true">
                    {{-- Os botoes da janela, do jeito que o sistema desenha: a
                         esquerda, e o nome do arquivo centrado entre eles e o
                         vao do mesmo tamanho do outro lado. --}}
                    <div class="flex items-center gap-3 border-b border-white/10 pb-3">
                        <span class="flex shrink-0 items-center gap-1.5">
                            <i class="size-3 rounded-full bg-[#ff5f57]"></i>
                            <i class="size-3 rounded-full bg-[#febc2e]"></i>
                            <i class="size-3 rounded-full bg-[#28c840]"></i>
                        </span>

                        <span class="relative block h-4 flex-1 text-center text-xs text-white/40">
                            @foreach ($operacoes as $operacao)
                                <span class="troca-operacao {{ $operacao['parada'] ? 'troca-operacao-parada' : '' }} absolute inset-0"
                                      style="--atraso: {{ $operacao['atraso'] }}">{{ $operacao['arquivo'] }}</span>
                            @endforeach
                        </span>

                        <span class="w-12 shrink-0"></span>
                    </div>

                    {{-- As tres operacoes dividem o mesmo espaco, empilhadas.
                         Altura fixa: sem ela o painel mudaria de tamanho a cada
                         troca, e a coluna ao lado pularia junto. --}}
                    <div class="relative mt-4 h-[7.5rem]">
                        @foreach ($operacoes as $operacao)
                            <div class="troca-operacao {{ $operacao['parada'] ? 'troca-operacao-parada' : '' }} absolute inset-0 space-y-2 leading-relaxed text-white/70"
                                 style="--atraso: {{ $operacao['atraso'] }}">
                                @foreach ($operacao['linhas'] as $indice => [$chave, $valor])
                                    <p class="digita" style="--atraso: {{ $tempos[$indice] }}">
                                        <span class="text-brand-300">{{ $chave }}</span> {{ $valor }}
                                    </p>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    {{-- As duas fases se revezam no mesmo lugar: executando
                         enquanto as linhas se escrevem, concluido depois. Altura
                         fixa para a troca nao mexer no tamanho do painel. --}}
                    <div class="relative mt-4 h-6 border-t border-white/10 pt-3 text-xs">
                        <span class="fase-terminal absolute inset-x-0 top-3 flex items-center gap-2 text-white/50" style="--atraso: 0s">
                            <i class="size-1.5 shrink-0 animate-pulse rounded-full bg-warning-400"></i>
                            executando
                            <i class="pisca inline-block h-3 w-1.5 bg-white/60"></i>
                        </span>

                        <span class="fase-terminal fase-terminal-parada absolute inset-x-0 top-3 flex items-center gap-2 text-success-400" style="--atraso: 4s">
                            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            status: concluido
                        </span>
                    </div>
                </div>
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
