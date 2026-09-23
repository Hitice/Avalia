@extends('layouts.site', [
    'titulo' => 'Perguntas frequentes',
    'descricao' => 'Respostas sobre prazos, investimento, integração com o ERP, segurança dos dados e como começar um projeto com a Avalia.',
])

@php
    use App\Support\Empresa;

    $perguntas = [
        [
            'Preciso trocar o meu ERP ou os sistemas que já uso?',
            'Não. Nossas soluções se conectam aos sistemas que a sua empresa já utiliza, como ERP, bancos e WhatsApp. A ideia é fazer o que existe trabalhar melhor, e não começar do zero.',
        ],
        [
            'Posso contratar apenas um software?',
            'Sim. Cada solução resolve uma rotina específica e pode ser contratada sozinha. Se mais tarde fizer sentido, elas se integram entre si.',
        ],
        [
            'Vocês também desenvolvem sites e sistemas?',
            'Sim. Além das automações e do atendimento humanizado, desenvolvemos sites, plataformas SaaS e web apps sob medida, que podem se integrar às demais soluções.',
        ],
        [
            'Quanto tempo leva um projeto?',
            'Depende do processo e das integrações envolvidas. Depois de entender a sua rotina, informamos na proposta o prazo e as etapas de entrega.',
        ],
        [
            'Como é definido o investimento?',
            'Com base no escopo. Você recebe uma proposta com escopo e investimento definidos antes de qualquer compromisso.',
        ],
        [
            'Como vocês tratam os dados da minha empresa?',
            'Tratamos os dados de acordo com a Lei Geral de Proteção de Dados (LGPD), com acesso restrito ao necessário para cada projeto.',
        ],
        [
            'O chat e a URA do WhatsApp usam o número da minha empresa?',
            'Sim. A integração é feita pela API do WhatsApp Business, com o número da sua empresa, seguindo as regras de uso da plataforma.',
        ],
        [
            'Como começamos?',
            'Pelo contato. Você conta o que quer automatizar ou desenvolver, conversamos para entender o processo e enviamos uma proposta clara, com escopo e investimento definidos.',
        ],
    ];
@endphp

@section('content')
    <x-site.cabecalho selo="Dúvidas" icone="M9.5 9.2a2.6 2.6 0 1 1 3.4 2.5c-.6.2-.9.8-.9 1.4v.6M12 17h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" titulo="Perguntas frequentes">
        O que costumam nos perguntar antes de começar um projeto. Se a sua dúvida não estiver
        aqui, fale com a gente.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            {{-- Uma aberta por vez. O grupo `name` deixa o proprio navegador
                 fechar a anterior, sem script: lista com tudo aberto vira uma
                 parede de texto e ninguem acha a propria pergunta. --}}
            <div class="mx-auto max-w-3xl space-y-3">
                @foreach ($perguntas as [$pergunta, $resposta])
                    <details name="duvida" class="cartao group px-6 py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-gray-900">
                            {{ $pergunta }}
                            <svg class="size-5 shrink-0 text-gray-400 transition group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                            </svg>
                        </summary>
                        <p class="mt-3 leading-relaxed text-gray-600">{{ $resposta }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <x-site.chamada titulo="Ficou alguma dúvida?" acao="Falar com a {{ Empresa::marca() }}">
        Fale com a gente pelo WhatsApp ou por e-mail e conte o que a sua empresa precisa.
    </x-site.chamada>
@endsection
