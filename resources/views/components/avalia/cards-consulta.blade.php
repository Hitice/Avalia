{{-- A grade de cards de consulta, unica para os tres acessos.

     Cliente, vendedor/representante e administrador consultam pela MESMA
     grade: card com estrelas de quartil, popup com resumo e preco, e o botao
     Consultar trocando o miolo pelo campo do documento. O que muda entre os
     acessos e so o destino do formulario e a linha de franquia (que apenas o
     cliente tem), entao isso entra por props e o resto vive aqui uma vez.
     Dropdown de muitos itens esconde o catalogo e nao tem onde dizer o que
     cada pesquisa traz nem quanto vale.

     Erro de validacao reabre direto o card do servico tentado, ja no
     formulario, com o documento digitado preservado. --}}
@props(['servicos', 'precos', 'estrelas', 'acao', 'franquias' => []])

@php
    use App\Support\Dinheiro;
    use App\Support\Laudo;
@endphp

<div x-data="{ aberto: {{ old('servico_id') ? (int) old('servico_id') : 'null' }},
               etapa: '{{ old('servico_id') ? 'form' : 'resumo' }}' }"
     class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($servicos as $servico)
        <div class="cartao overflow-hidden transition"
             :class="aberto === {{ $servico->id }}
                 ? 'border-brand-400 shadow-theme-md dark:border-brand-500/60'
                 : 'hover:border-brand-300 hover:shadow-sm dark:hover:border-brand-500/40'">

            @php $nEstrelas = $estrelas[$servico->id] ?? 0; @endphp

            {{-- Altura fixa e nome em duas linhas no maximo: card de tamanho
                 variavel quebra a grade e o olho perde a comparacao. --}}
            <button type="button" class="flex min-h-[104px] w-full flex-col justify-between px-5 py-4 text-left"
                    @click="aberto = aberto === {{ $servico->id }} ? null : {{ $servico->id }}; etapa = 'resumo'">
                <span class="flex w-full items-start justify-between gap-3">
                    <span class="line-clamp-2 font-medium text-gray-800 dark:text-white/90">
                        {{ $servico->nome }}
                    </span>

                    {{-- Zero a tres estrelas pelo quartil de preco, que no
                         catalogo acompanha a profundidade da pesquisa. As
                         apagadas ficam na tela: zero e quartil de entrada,
                         nao defeito, e a regua so se le inteira. --}}
                    <span class="flex shrink-0 gap-0.5 pt-1" title="{{ $nEstrelas }} de 3">
                        @foreach (['text-brand-400', 'text-theme-purple-500', 'text-theme-pink-500'] as $i => $cor)
                            <svg class="size-3 {{ $i < $nEstrelas ? $cor : 'text-gray-200 dark:text-gray-700' }}"
                                 fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.956a1 1 0 0 0 .95.69h4.161c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.363 1.118l1.287 3.956c.3.922-.755 1.688-1.538 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.196-1.539-1.118l1.287-3.956a1 1 0 0 0-.363-1.118L2.114 9.383c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 0 0 .95-.69L9.05 2.927Z" />
                            </svg>
                        @endforeach
                        <span class="sr-only">{{ $nEstrelas }} de 3 estrelas</span>
                    </span>
                </span>

                <span class="mt-2 flex w-full items-center justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">{{ $servico->rotuloCategoria() }}</span>
                    <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">
                        {{ isset($precos[$servico->id]) ? Dinheiro::brl($precos[$servico->id]) : 'Sob consulta' }}
                    </span>
                </span>
            </button>

            {{-- O detalhe abre SOBRE a tela, centrado no escopo da aba e
                 desconsiderando a coluna esquerda, como no resto do sistema. --}}
            <template x-teleport="body">
                <div x-cloak x-show="aberto === {{ $servico->id }}" x-transition.opacity.duration.200ms
                     class="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4"
                     :class="($store.sidebar.isExpanded || $store.sidebar.isHovered) ? 'xl:pl-[290px]' : 'xl:pl-[90px]'"
                     @keydown.escape.window="aberto = null">
                    <div class="entra-popup w-full max-w-2xl rounded-2xl bg-white shadow-theme-lg dark:bg-gray-800"
                         @click.outside="aberto = null">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <div>
                                <h2 class="font-medium text-gray-800 dark:text-white/90">{{ $servico->nome }}</h2>
                                <p class="ajuda-campo mt-0.5">
                                    {{ $servico->rotuloCategoria() }}
                                    · {{ isset($precos[$servico->id]) ? Dinheiro::brl($precos[$servico->id]) : 'Sob consulta' }}
                                </p>
                            </div>
                            <button type="button" @click="aberto = null"
                                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                                </svg>
                                <span class="sr-only">Fechar</span>
                            </button>
                        </div>

                        <div class="px-6 py-5">
                            <div x-show="etapa === 'resumo'">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ $servico->descricao ?: 'Consulta ao documento nas bases contratadas deste serviço.' }}
                                </p>

                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                    O resultado abre na hora, em relatório com a marca da Avalia:
                                    score com régua de temperatura, identificação do titular,
                                    restrições da mais grave para a menos e o contexto de consultas
                                    recentes. O que a base não trouxer aparece dito com todas as
                                    letras, e o laudo sai pronto para imprimir ou anexar.
                                </p>

                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                    O documento consultado fica mascarado no arquivo, o protocolo
                                    vai no rodapé de toda página, e a consulta é registrada com
                                    quem consultou e a finalidade declarada: pesquisa de
                                    avaliação de risco.
                                </p>

                                @if ($franquias[$servico->id] ?? null)
                                    <p class="ajuda-campo mt-2">{{ $franquias[$servico->id] }}</p>
                                @endif

                                @php
                                    $bases = collect(explode(',', (string) $servico->fornecedor))
                                        ->map(fn ($f) => trim($f))
                                        ->filter(fn ($f) => $f !== '' && $f !== 'simulado')
                                        ->map(fn ($f) => Laudo::nomeDaFonte($f));
                                @endphp

                                @if ($bases->isNotEmpty())
                                    <p class="ajuda-campo mt-2">Bases: {{ $bases->implode(' · ') }}</p>
                                @endif

                                <div class="mt-5 text-right">
                                    <x-avalia.botao type="button"
                                                    x-on:click="etapa = 'form'; $nextTick(() => $refs.doc{{ $servico->id }}.focus())">
                                        Consultar
                                    </x-avalia.botao>
                                </div>
                            </div>

                            <form x-show="etapa === 'form'" method="POST" action="{{ $acao }}">
                                @csrf
                                <input type="hidden" name="servico_id" value="{{ $servico->id }}">

                                <label for="documento-{{ $servico->id }}" class="rotulo-campo">CPF ou CNPJ</label>
                                <input id="documento-{{ $servico->id }}" name="documento" type="text"
                                       x-ref="doc{{ $servico->id }}"
                                       class="campo mt-1" required inputmode="numeric"
                                       value="{{ old('servico_id') == $servico->id ? old('documento') : '' }}"
                                       placeholder="Só números ou com máscara">
                                @if (old('servico_id') == $servico->id)
                                    @error('documento') <span class="erro-campo">{{ $message }}</span> @enderror
                                @endif

                                <div class="mt-4 flex items-center justify-between gap-3">
                                    <button type="button" x-on:click="etapa = 'resumo'"
                                            class="hover:text-brand-600 dark:hover:text-brand-400 text-sm text-gray-500 dark:text-gray-400">
                                        Voltar ao resumo
                                    </button>

                                    <x-avalia.botao>Confirmar consulta</x-avalia.botao>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endforeach
</div>
