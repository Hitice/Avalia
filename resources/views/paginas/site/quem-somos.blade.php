@extends('layouts.site', [
    'titulo' => 'Quem somos',
    'descricao' => 'Software house de produtos digitais com foco em controle fiscal, finanças, marketing e investimentos: automação, atendimento humanizado e sistemas sob medida.',
])

@php
    use App\Support\Empresa;
@endphp

@section('content')
    <x-site.cabecalho selo="Sobre" icone="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M13 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM20 19v-1.5a3.5 3.5 0 0 0-2.6-3.4M15.5 5a3 3 0 0 1 0 5" titulo="Quem somos" />

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="prosa">
                <p>
                    A {{ Empresa::marca() }} é uma software house de produtos digitais com foco em
                    controle fiscal, finanças, marketing e investimentos.
                </p>
                <p>
                    Desenvolvemos automações, atendimentos humanizados com IA e sistemas sob medida:
                    do chat e da URA no WhatsApp à automação de processos, da análise de mercado à
                    cobrança, do controle de produção a sites, SaaS e web apps.
                </p>
                <p>
                    Trabalhamos lado a lado com a sua equipe: entendemos o processo antes de escrever
                    a primeira linha de código e entregamos em etapas, para que o resultado apareça cedo.
                </p>
                <p>
                    Além do trabalho sob demanda, mantemos plataformas próprias: o
                    {{ Empresa::marcaCredito() }}, de pesquisa de score para venda a prazo, e o
                    {{ Empresa::marcaCobranca() }}, de venda parcelada, cobrança e APIs.
                </p>
            </div>

            <div class="mt-12 grid gap-5 lg:grid-cols-2">
                <div class="cartao p-6">
                    <h2 class="rotulo-grupo">Áreas de foco</h2>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach (['Controle fiscal', 'Finanças', 'Marketing', 'Investimentos'] as $area)
                            <li class="etiqueta etiqueta-neutra">{{ $area }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="cartao p-6">
                    <h2 class="rotulo-grupo">O que desenvolvemos</h2>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ([
                            'Chat e atendimento humanizados', 'URA com IA', 'Automação de processos',
                            'Análise de mercado', 'Integração de sistemas', 'Automação de cobranças',
                            'Controle de produção e CRM', 'Sites, SaaS e web apps',
                        ] as $item)
                            <li class="etiqueta etiqueta-neutra">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Onde a casa fica. A matriz responde pelo CNPJ; Florianopolis e
                 o braco de software house, sem inscricao propria, e por isso
                 aparece como endereco e nao ao lado de um registro. --}}
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div class="cartao p-6">
                    <h2 class="rotulo-grupo">Matriz</h2>
                    <p class="mt-3 leading-relaxed text-gray-600">{{ Empresa::endereco() }}</p>
                    <p class="mt-2 text-sm text-gray-500">CNPJ {{ Empresa::cnpj() }}</p>
                </div>

                <div class="cartao p-6">
                    <h2 class="rotulo-grupo">{{ Empresa::bracoRotulo() }}</h2>
                    <p class="mt-3 leading-relaxed text-gray-600">{{ Empresa::bracoEndereco() }}</p>
                    <p class="mt-2 text-sm text-gray-500">Unidade da mesma empresa, sem inscrição própria.</p>
                </div>
            </div>
        </div>
    </section>

    <x-site.chamada titulo="Quer conversar sobre um projeto?">
        Conte o que você quer automatizar ou desenvolver. Respondemos com uma proposta clara,
        com escopo e investimento definidos.
    </x-site.chamada>
@endsection
