{{-- O relatorio da consulta num popup, sem sair da pagina.

     Abrir o PDF na propria aba tirava o operador do lugar onde ele estava: no
     portal, um clique em voltar e a pessoa ja nao sabe onde parou. O visor
     mantem a pagina embaixo e mostra o relatorio por cima, como o leitor de
     termos ja faz.

     O PDF vem por iframe porque o endereco ja serve o arquivo inline: o
     navegador desenha o visualizador nativo dele dentro do quadro, com zoom e
     impressao de graca. "Abrir em nova aba" continua ali para quem quer o
     arquivo solto, e o proprio visualizador tem o botao de salvar. --}}
@props(['url', 'rotulo' => 'Ver relatório', 'titulo' => 'Relatório da consulta', 'aberto' => false])

<div x-data="{ aberto: {{ $aberto ? 'true' : 'false' }} }" class="inline">
    <button type="button" @click="aberto = true" {{ $attributes->class(['botao botao-secundario botao-sm']) }}>
        {{ $rotulo }}
    </button>

    <template x-teleport="body">
        <div x-cloak x-show="aberto" x-transition.opacity.duration.300ms
             class="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4"
             :class="($store.sidebar?.isExpanded || $store.sidebar?.isHovered) ? 'xl:pl-[290px]' : 'xl:pl-[90px]'"
             @keydown.escape.window="aberto = false">
            <div class="entra-popup flex h-[90vh] w-full max-w-4xl flex-col rounded-2xl bg-white shadow-theme-lg dark:bg-gray-800"
                 @click.outside="aberto = false">
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                    <h2 class="font-medium text-gray-800 dark:text-white/90">{{ $titulo }}</h2>

                    <div class="flex items-center gap-2">
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="hover:text-brand-600 dark:hover:text-brand-400 text-sm text-gray-500 dark:text-gray-400">
                            Abrir em nova aba
                        </a>

                        <button type="button" @click="aberto = false"
                                class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                            </svg>
                            <span class="sr-only">Fechar</span>
                        </button>
                    </div>
                </div>

                {{-- So carrega o PDF quando abre: iframe pronto na pagina
                     geraria o arquivo em toda visita, aberto ou nao. --}}
                <template x-if="aberto">
                    <iframe src="{{ $url }}" title="{{ $titulo }}" class="grow rounded-b-2xl bg-gray-100 dark:bg-gray-900"></iframe>
                </template>
            </div>
        </div>
    </template>
</div>
