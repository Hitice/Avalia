@props([
    'titulo',
    'acao' => 'Solicitar proposta',
    'href' => null,
])

{{--
    A faixa de proximo passo, no pe das paginas do site.

    Sempre a mesma pergunta e sempre o mesmo botao: a pagina que terminava sem
    saida deixava o visitante interessado sem nada para clicar.
--}}

<section class="pb-16 lg:pb-24">
    <div class="mx-auto w-full max-w-[87rem] px-6">
        <div class="cartao flex flex-col items-start gap-6 p-8 lg:flex-row lg:items-center lg:justify-between lg:p-10">
            <div class="max-w-xl">
                <h2 class="text-xl font-semibold text-gray-900">{{ $titulo }}</h2>
                <p class="mt-2 leading-relaxed text-gray-600">{{ $slot }}</p>
            </div>

            <x-avalia.botao :href="$href ?? route('site.contato')" class="shrink-0">
                {{ $acao }}
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </x-avalia.botao>
        </div>
    </div>
</section>
