@props(['servico'])

{{--
    O cartao de um servico digital.

    Aparece no indice de /servicos-digitais e na secao dentro de /softwares.
    Componente, e nao markup repetido: os dois lugares mostram a mesma oferta,
    e escrita duas vezes uma delas sai desatualizada no dia em que um servico
    entrar ou mudar de nome.
--}}

<a href="{{ $servico['rota'] ? route($servico['rota']) : '#' }}"
   class="bloco group flex flex-col p-7" data-revelar>
    <span class="icone-caixa">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $servico['icone'] }}" />
        </svg>
    </span>

    <h3 class="mt-5 flex flex-wrap items-center gap-2 text-lg font-semibold tracking-tight text-gray-900">
        {{ $servico['titulo'] }}

        @if ($servico['selo'])
            <span class="etiqueta etiqueta-sucesso">{{ $servico['selo'] }}</span>
        @endif
    </h3>

    <p class="mt-3 flex-1 leading-relaxed text-gray-600">{{ $servico['resumo'] }}</p>

    <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-500 transition group-hover:gap-3">
        Ver o serviço
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
        </svg>
    </span>
</a>
