{{-- Leitura com aceite no fim, como termo de aparelho novo: o texto abre num
     popup com rolagem vigiada e o aceite so destrava quando a leitura chega ao
     fim. O texto exibido e o mesmo que gera o PDF, e o hash enviado fecha a
     janela entre ler e aceitar. Documento de apoio abre o mesmo leitor, sem
     formulario. --}}
@props(['documento', 'acao' => null, 'aceito' => false])

<div x-data="{ aberto: false, liberado: false }" class="inline">
    <button type="button" @click="aberto = true"
            class="botao botao-secundario botao-sm">
        {{ $acao && ! $aceito && $documento->exige_aceite ? 'Ler e aceitar' : 'Ler documento' }}
    </button>

    <template x-teleport="body">
        <div x-cloak x-show="aberto" x-transition.opacity.duration.300ms
             class="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="aberto = false">
            <div class="entra-popup flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-theme-lg dark:bg-gray-800"
                 @click.outside="aberto = false">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <div>
                        <h2 class="font-medium text-gray-800 dark:text-white/90">{{ $documento->titulo }}</h2>
                        <span class="ajuda-campo">Versão {{ $documento->versao }}</span>
                    </div>
                    <button type="button" @click="aberto = false"
                            class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                        <span class="sr-only">Fechar</span>
                    </button>
                </div>

                {{-- A rolagem vigiada: chegou ao fim, libera. Texto que cabe
                     inteiro sem rolar ja nasce liberado. --}}
                <div class="grow overflow-y-auto px-6 py-5 text-sm whitespace-pre-line text-gray-700 dark:text-gray-300"
                     x-init="$nextTick(() => { if ($el.scrollHeight <= $el.clientHeight + 8) liberado = true })"
                     @scroll="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) liberado = true">{{ $documento->conteudo }}</div>

                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-700">
                    @if ($acao && ! $aceito && $documento->exige_aceite)
                        <form method="POST" action="{{ $acao }}" class="flex flex-wrap items-end gap-4">
                            @csrf
                            <input type="hidden" name="hash" value="{{ $documento->hashConteudo() }}">

                            <div class="min-w-56 grow sm:max-w-xs">
                                <label for="responsavel-{{ $documento->id }}" class="rotulo-campo">Quem está aceitando</label>
                                <input id="responsavel-{{ $documento->id }}" name="responsavel" type="text"
                                       class="campo" required minlength="5" maxlength="150"
                                       value="{{ old('responsavel') }}" placeholder="Nome completo">
                            </div>

                            <label class="rotulo-opcao pb-2.5">
                                <input type="checkbox" name="li" value="1" required :disabled="! liberado"
                                       class="text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                Li o documento na íntegra
                            </label>

                            <button type="submit" :disabled="! liberado"
                                    class="botao botao-primario botao-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="liberado">Aceitar</span>
                                <span x-cloak x-show="! liberado">Role até o fim para aceitar</span>
                            </button>
                        </form>
                    @else
                        <button type="button" @click="aberto = false" class="botao botao-secundario botao-sm">Fechar</button>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
