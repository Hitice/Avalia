@extends('layouts.site', [
    'titulo' => 'Automação e software sob medida',
    'descricao' => 'Software sob medida que conecta sistemas e pessoas: automação de processos, atendimento humanizado com IA, cobrança, análise de mercado e integração de sistemas.',
])

@php
    use App\Support\Empresa;

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
        ['papel' => 'ENTRADA', 'nome' => 'ERP', 'legenda' => 'Lendo as notas do ERP', 'pct' => 31,
            'atraso' => '0s', 'saida' => '0.55s', 'parado' => false],
        ['papel' => 'MOTOR', 'nome' => Empresa::marca(), 'legenda' => 'Aplicando as regras do negócio', 'pct' => 68,
            'atraso' => '2s', 'saida' => '2.55s', 'parado' => true],
        ['papel' => 'SAÍDA', 'nome' => 'Banco', 'legenda' => 'Conciliando lançamentos', 'pct' => 94,
            'atraso' => '4s', 'saida' => null, 'parado' => false],
    ];

    // As tres operacoes que o terminal roda em rodizio, uma por vez, num ciclo
    // de 24s. Uma so, repetindo para sempre, dizia que a casa faz uma coisa; o
    // rodizio mostra tres frentes diferentes trabalhando, que e o que a secao
    // ao lado promete.
    //
    // Cada uma e um comando com a saida dele, e nao um trecho de arquivo
    // parado: o comando se escreve, as linhas de resposta chegam uma a uma e o
    // fecho confirma. Terminal de verdade e o que a secao esta dizendo que
    // acontece, e mostrar um YAML nao dizia que algo estava sendo executado.
    //
    // O atraso de cada operacao e o terco dela no ciclo; os das linhas sao o
    // instante em que cada resposta chega, dentro dos 8s da operacao.
    $operacoes = [
        [
            'nome' => 'fiscal',
            'atraso' => '0s',
            // Fica visivel para quem pediu menos movimento: e a operacao que o
            // texto ao lado descreve.
            'parada' => true,
            'comando' => 'avalia run fiscal --evento=nota_fiscal.recebida',
            'saidas' => [
                ['linha' => 'validando contra regras_fiscais.br', 'atraso' => '1.4s'],
                ['linha' => 'conciliando com extrato.bancario', 'atraso' => '2.2s'],
                ['linha' => 'notificando equipe.financeiro', 'atraso' => '3s'],
            ],
            'fecho' => 'lançamento conciliado',
        ],
        [
            'nome' => 'cobranca',
            'atraso' => '8s',
            'parada' => false,
            'comando' => 'avalia run cobranca --evento=parcela.venceu',
            'saidas' => [
                ['linha' => 'consultando titulos_em_aberto', 'atraso' => '1.4s'],
                ['linha' => 'enviando lembrete.whatsapp', 'atraso' => '2.2s'],
                ['linha' => 'aguardando extrato.conciliado', 'atraso' => '3s'],
            ],
            'fecho' => 'régua em dia',
        ],
        [
            'nome' => 'atendimento',
            'atraso' => '16s',
            'parada' => false,
            'comando' => 'avalia run atendimento --evento=mensagem.recebida',
            'saidas' => [
                ['linha' => 'classificando intencao_do_cliente', 'atraso' => '1.4s'],
                ['linha' => 'respondendo em ura.voz_natural', 'atraso' => '2.2s'],
                ['linha' => 'transferindo para fila.atendente', 'atraso' => '3s'],
            ],
            'fecho' => 'cliente atendido',
        ],
    ];

@endphp

@section('content')
    <div x-data="{ aberto: null }" @keydown.escape.window="aberto = null">
    {{-- Herói. A grade escura da marca ao fundo, a promessa na frente. --}}
    <section class="superficie-escura relative overflow-hidden">
        {{-- A faixa do herói mede oito quadradinhos de altura: 8 x 42px, o
             passo da grade. Altura fixa, e nao folga vertical, porque o que se
             quer e exatamente isto: a grade fechando em oito linhas inteiras e
             a secao seguinte comecando a aparecer sem ninguem precisar rolar.
             No celular a altura volta a ser a do conteudo, que empilha. --}}
        <div class="grade-viva-escura lg:h-[336px]">
            <div class="mx-auto grid h-full w-full max-w-[87rem] items-center gap-8 px-6 pt-[76px] pb-12 lg:grid-cols-2 lg:pb-0">
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
                            {{-- O numero anda com o estagio, e nao fica parado
                                 num valor so: parado, ele dizia que o painel e
                                 uma foto. Sobe a cada etapa porque e progresso
                                 de uma operacao, e nao um numero sorteado. --}}
                            <strong class="relative block h-5 w-11 shrink-0 text-right text-white">
                                @foreach ($fluxo as $estagio)
                                    <span class="troca-legenda {{ $estagio['parado'] ? 'troca-legenda-parada' : '' }} absolute inset-0"
                                          style="--atraso: {{ $estagio['atraso'] }}">{{ $estagio['pct'] }}%</span>
                                @endforeach
                            </strong>
                        </div>

                        <div class="relative mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            @foreach ($fluxo as $estagio)
                                <i class="troca-legenda {{ $estagio['parado'] ? 'troca-legenda-parada' : '' }} barra-bureau absolute inset-y-0 left-0 rounded-full"
                                   style="--atraso: {{ $estagio['atraso'] }}; width: {{ $estagio['pct'] }}%"></i>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-site.trilho />
    </section>

    {{-- A vitrine de software sob demanda. --}}
    <section class="py-20 lg:py-24">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="indice">01 / Softwares</span>
                    <h2 class="mt-3 text-title-sm font-semibold tracking-tight text-gray-900">
                        Mais produtividade à equipe, execução com <span class="texto-bureau">controle</span> e <span class="texto-bureau">precisão</span>.
                    </h2>
                </div>
                <p class="max-w-md text-gray-600">
                    Software personalizado para cada necessidade. Conectadas, as soluções compartilham
                    dados, aumentam a produtividade e ganham escala.
                </p>
            </div>

            {{-- O cartao abre o detalhe aqui mesmo, num popup, em vez de mandar
                 para a pagina de softwares e rolar ate a ancora.

                 O salto para outra pagina cobrava duas navegacoes de quem so
                 queria saber o que uma frente faz: descer ate ela e voltar. O
                 popup traz a frente ao foco e devolve a vitrine inteira com um
                 clique fora, que e o gesto que a pessoa ja faz. O mesmo padrao
                 dos pilares da pagina do produto de score. --}}
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($softwares as $ancora => $software)
                    <button type="button" @click="aberto = '{{ $ancora }}'" class="bloco group text-left"
                            data-revelar style="--atraso: {{ $loop->index * 0.07 }}s">
                        <div class="flex items-start justify-between">
                            <span class="icone-caixa">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $software['icone'] }}" />
                                </svg>
                            </span>
                            <code class="text-xs font-medium text-gray-300">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</code>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $software['titulo'] }}</h3>
                        <p class="text-sm leading-relaxed text-gray-600">{{ $software['resumo'] }}</p>
                        <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                            Saiba mais
                            <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </span>
                    </button>
                @endforeach
            </div>

            <a href="{{ route('site.softwares') }}#rpa"
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

    {{-- As aplicações da casa.

         A holding opera negócios próprios e vende software sob demanda, e a
         porta de cada negócio precisa aparecer aqui: quem chega pelo domínio
         procurando o sistema que já usa não devia ter que adivinhar por onde
         se entra. --}}
    <section id="aplicacoes" class="relative scroll-mt-[60px] overflow-hidden bg-gray-50 py-20 lg:py-24">
        {{-- Um pedaco da marca, grande e em rosa apagado, saindo pelo canto de
             baixo. Encostada no pe da secao, e nao no topo: la ela disputava
             com o titulo, que e a primeira coisa a ser lida.

             E o simbolo do medidor da propria Avalia, recortado pela borda: um
             desenho generico ali seria enfeite, e este diz de quem e a pagina.
             Fica atras do conteudo e fora da arvore de acessibilidade, porque
             e textura, nao informacao. --}}
        <svg class="pointer-events-none absolute -right-28 -bottom-32 hidden w-[30rem] text-theme-pink-500/[0.07] lg:block"
             viewBox="0 0 32 32" fill="none" aria-hidden="true">
            <path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            <path d="M16 22.5 22.3 14.6" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            <circle cx="16" cy="22.5" r="2.6" fill="currentColor" />
        </svg>

        <div class="relative mx-auto w-full max-w-[87rem] px-6">
            <div class="max-w-3xl">
                <span class="indice">02 / Aplicações</span>
                <h2 class="mt-3 text-title-sm font-semibold tracking-tight text-gray-900">
                    Negócios próprios, <span class="texto-bureau">no ar</span> e em operação.
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-gray-600">
                    Conheça nossas plataformas em destaque.
                </p>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-2">
                <a href="{{ route('credito') }}" class="bloco group" data-revelar>
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

                <a href="{{ route('cobranca') }}" class="bloco group" data-revelar style="--atraso: 0.1s">
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

    {{-- Como funciona: do evento à decisão.

         O cabecalho ocupa a largura inteira e as duas colunas comecam juntas
         abaixo dele. Antes o titulo morava dentro da coluna da esquerda e o
         terminal ficava centralizado na altura da secao: ele nascia no meio do
         nada, sem alinhar com passo nenhum da lista ao lado. --}}
    <section class="superficie-escura grade-viva-escura">
        <div class="mx-auto w-full max-w-[87rem] px-6 py-14 lg:py-16">
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
                        ['Visão inteligente', 'Validações e rotinas rodam sozinhas, nas regras do seu negócio.'],
                        ['Equipe no controle', 'A exceção chega a quem decide, com a informação pronta.'],
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
                    {{-- Nome da operacao a esquerda, sinal de vida a direita. --}}
                    <div class="flex items-center gap-3 border-b border-white/10 pb-3">
                        <span class="relative block h-4 flex-1 text-xs text-white/40">
                            @foreach ($operacoes as $operacao)
                                <span class="troca-operacao {{ $operacao['parada'] ? 'troca-operacao-parada' : '' }} absolute inset-0"
                                      style="--atraso: {{ $operacao['atraso'] }}">avalia@fluxo: {{ $operacao['nome'] }}</span>
                            @endforeach
                        </span>

                        <i class="size-2.5 shrink-0 rounded-full bg-success-400"></i>
                    </div>

                    {{-- As tres operacoes dividem o mesmo espaco, empilhadas.
                         Altura fixa: sem ela o painel mudaria de tamanho a cada
                         troca, e a coluna ao lado pularia junto. --}}
                    <div class="relative mt-4 h-[9.5rem] text-xs leading-relaxed sm:text-sm">
                        @foreach ($operacoes as $operacao)
                            <div class="troca-operacao {{ $operacao['parada'] ? 'troca-operacao-parada' : '' }} absolute inset-0"
                                 style="--atraso: {{ $operacao['atraso'] }}">
                                {{-- O comando, que se escreve sozinho, com o cursor no fim. --}}
                                <p class="flex items-baseline gap-2">
                                    <span class="shrink-0 text-success-400">$</span>
                                    <span class="digita min-w-0 text-white/90" style="--atraso: 0s">{{ $operacao['comando'] }}</span>
                                    <i class="pisca inline-block h-3 w-1.5 shrink-0 bg-white/60"></i>
                                </p>

                                {{-- A resposta, uma linha por vez. --}}
                                <div class="mt-2 space-y-1.5">
                                    @foreach ($operacao['saidas'] as $saida)
                                        <p class="surge-linha flex items-baseline gap-2 text-white/55" style="--atraso: {{ $saida['atraso'] }}">
                                            <span class="shrink-0 text-brand-300">›</span>
                                            <span class="min-w-0 truncate">{{ $saida['linha'] }}</span>
                                            <span class="ml-auto shrink-0 text-success-400">ok</span>
                                        </p>
                                    @endforeach
                                </div>
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
                        </span>

                        @foreach ($operacoes as $operacao)
                            <span class="troca-operacao {{ $operacao['parada'] ? 'troca-operacao-parada' : '' }} absolute inset-x-0 top-3"
                                  style="--atraso: {{ $operacao['atraso'] }}">
                                <span class="fase-terminal flex items-center gap-2 text-success-400" style="--atraso: 4s">
                                    <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ $operacao['fecho'] }}
                                </span>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Quem somos, resumido. O texto inteiro mora na própria página. --}}
    <section class="py-20 lg:py-24">
        <div class="mx-auto grid w-full max-w-[87rem] items-center gap-10 px-6 lg:grid-cols-[1fr_24rem] lg:gap-16">
            <div data-revelar>
                <span class="indice">04 / Quem somos</span>
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

            {{-- A unica presenca humana da pagina. O resto dela e painel,
                 diagrama e terminal, e uma pagina inteira de interface nao
                 lembra que quem entrega o trabalho sao pessoas. --}}
            <figure class="hidden overflow-hidden rounded-2xl border border-gray-200 lg:block" data-revelar style="--atraso: 0.1s">
                <img src="{{ asset('images/site/equipe-reuniao.jpg') }}" width="1024" height="796" loading="lazy"
                     alt="Equipe reunida em volta de uma mesa, conversando sobre o trabalho"
                     class="aspect-[4/3] w-full object-cover">
            </figure>
        </div>
    </section>

    {{-- Próximo passo. --}}
    <section class="pb-20 lg:pb-24">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            {{-- A foto entra por tras do fecho, bem escurecida: ela da
                 profundidade a faixa sem disputar com o texto, que e o que
                 precisa ser lido ali. A grade da marca continua por cima. --}}
            <div class="superficie-escura relative flex flex-col items-start gap-6 overflow-hidden rounded-2xl p-8 lg:flex-row lg:items-center lg:justify-between lg:p-12">
                <img src="{{ asset('images/site/infraestrutura.jpg') }}" alt="" aria-hidden="true" loading="lazy"
                     class="absolute inset-0 size-full object-cover opacity-20">
                <div class="grade-viva-escura absolute inset-0"></div>

                <div class="relative max-w-xl">
                    <span class="indice indice-claro">Próximo passo</span>
                    <h2 class="mt-3 text-title-sm font-semibold tracking-tight">Vamos tirar uma rotina do caminho?</h2>
                    <p class="mt-3 leading-relaxed text-white/60">
                        Conte qual processo mais consome o tempo da sua equipe. Respondemos com uma
                        proposta clara, com escopo e investimento definidos.
                    </p>
                </div>
                <x-avalia.botao :href="route('site.contato')" class="relative shrink-0">
                    Solicitar proposta
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </x-avalia.botao>
            </div>
        </div>
    </section>
    {{-- O detalhe da frente escolhida. Um overlay so, com o conteudo trocando:
         sete overlays empilhados seriam sete copias da mesma moldura para
         divergir. Fecha no clique fora, no Esc e no botao. --}}
    <div x-cloak x-show="aberto !== null" x-transition.opacity.duration.200ms
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
         @click.self="aberto = null" role="dialog" aria-modal="true">
        @foreach ($softwares as $ancora => $software)
            <div x-cloak x-show="aberto === '{{ $ancora }}'" class="entra-popup relative w-full max-w-2xl">
                <div class="cartao max-h-[85vh] overflow-y-auto p-6 sm:p-8">
                    <button type="button" @click="aberto = null" aria-label="Fechar"
                            class="absolute top-4 right-4 flex size-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>

                    <span class="icone-caixa">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $software['icone'] }}" />
                        </svg>
                    </span>

                    <h3 class="mt-5 pr-10 text-2xl font-semibold tracking-tight text-gray-900">{{ $software['titulo'] }}</h3>
                    <p class="mt-3 leading-relaxed text-gray-600">{{ $software['texto'] }}</p>

                    @if ($software['imagem'])
                        <figure class="mt-6 overflow-hidden rounded-xl border border-gray-200">
                            <img src="{{ asset('images/softwares/'.$software['imagem']) }}"
                                 alt="{{ $software['alt'] }}" width="1200" height="750" loading="lazy"
                                 class="aspect-[16/10] w-full object-cover">
                        </figure>
                    @endif

                    <ul class="mt-6 space-y-3">
                        @foreach ($software['itens'] as $item)
                            <li class="flex items-start gap-3 text-sm leading-relaxed text-gray-600">
                                <svg class="mt-0.5 size-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <x-avalia.botao :href="route('site.contato')">
                            Solicitar proposta
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </x-avalia.botao>

                        {{-- O caminho para a pagina continua existindo: quem
                             quer ler as sete frentes de uma vez, e o buscador,
                             precisam dele. --}}
                        <x-avalia.botao variante="secundario" :href="route('site.softwares').'#'.$ancora">
                            Ver todos os softwares
                        </x-avalia.botao>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    </div>
@endsection