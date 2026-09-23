@php
    // O que a casa faz, correndo no pe da faixa escura. A lista mora aqui, e
    // nao em cada pagina: escrita duas vezes, uma delas ficaria sem o negocio
    // que entrou depois.
    $termos = [
        'Atendimento humanizado', 'Automação de processos', 'Análise de mercado', 'Cobranças',
        'Integração de sistemas', 'Controle de produção', 'CRM', 'Sites e SaaS',
    ];
@endphp

{{--
    O trilho de termos.

    Dois grupos identicos correndo juntos: quando o primeiro termina de sair, o
    segundo ja ocupa o lugar dele e a emenda nao aparece. Decorativo, entao sai
    da arvore de acessibilidade: quem usa leitor de tela ja leu os mesmos
    assuntos no menu e na pagina de softwares.
--}}

<div {{ $attributes->merge(['class' => 'overflow-hidden border-t border-white/10 py-4']) }} aria-hidden="true">
    <div class="trilho flex w-max items-center gap-8 text-sm whitespace-nowrap text-white/40">
        @foreach (array_merge($termos, $termos, $termos, $termos) as $termo)
            <span class="flex items-center gap-8">{{ $termo }}<i class="size-1 rounded-full bg-brand-500"></i></span>
        @endforeach
    </div>
</div>
