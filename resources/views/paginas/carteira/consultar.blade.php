@extends('layouts.app', ['title' => 'Consultar'])

@php
    use App\Support\Dinheiro;
    use App\Support\Laudo;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $vendedor->ehAdmin() ? 'Consultar' : 'Minha carteira' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            @if ($vendedor->ehAdmin())
                Consulta da operação: nenhuma empresa é cobrada e o custo do fornecedor entra
                no custo do período, sem comissão.
            @else
                Demonstração para fechar venda: consulte o documento do seu prospect e mostre
                o resultado na hora. Ninguém é cobrado; o custo sai da sua comissão.
            @endif
            Você ainda tem {{ $restantes }} {{ $restantes === 1 ? 'consulta' : 'consultas' }} hoje.
        </p>
    </div>

    @unless ($vendedor->ehAdmin())
        @include('paginas.carteira.abas')
    @endunless

    @if (session('erro'))
        <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
    @endif

    {{-- Cada serviço é um card, e não uma linha de dropdown: dropdown de 40
         itens esconde o catálogo atrás de um clique e não tem onde dizer o que
         cada pesquisa traz nem quanto vale. O card abre com o resumo e o botão
         Consultar troca o miolo pelo campo do documento, então a escolha e a
         consulta acontecem no mesmo lugar, sem mudar de tela. --}}
    <div x-data="{ aberto: null, etapa: 'resumo' }"
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

                <div x-cloak x-show="aberto === {{ $servico->id }}"
                     class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">

                    {{-- O resumo: o que a pesquisa traz, de onde vem e quanto vale. --}}
                    <div x-show="etapa === 'resumo'">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ $servico->descricao ?: 'Consulta ao documento nas bases contratadas, com resultado em relatório padronizado: score com temperatura, identificação, restrições e contexto.' }}
                        </p>

                        @php
                            $bases = collect(explode(',', (string) $servico->fornecedor))
                                ->map(fn ($f) => trim($f))
                                ->filter(fn ($f) => $f !== '' && $f !== 'simulado')
                                ->map(fn ($f) => Laudo::nomeDaFonte($f));
                        @endphp

                        @if ($bases->isNotEmpty())
                            <p class="ajuda-campo mt-2">Bases: {{ $bases->implode(' · ') }}</p>
                        @endif

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $vendedor->ehAdmin() ? 'Sem cobrança: custo da operação.' : 'Sem cobrança: sai da sua comissão.' }}
                            </span>

                            <x-avalia.botao tamanho="sm" type="button" x-on:click="etapa = 'form'">
                                Consultar
                            </x-avalia.botao>
                        </div>
                    </div>

                    {{-- O card vira o formulário: documento e enviar, nada mais. --}}
                    <form x-show="etapa === 'form'" method="POST"
                          action="{{ route('carteira.consultar.executar') }}">
                        @csrf
                        <input type="hidden" name="servico_id" value="{{ $servico->id }}">

                        <label for="documento-{{ $servico->id }}" class="rotulo-campo">CPF ou CNPJ</label>
                        <input id="documento-{{ $servico->id }}" name="documento" type="text"
                               class="campo mt-1" required inputmode="numeric"
                               x-bind:disabled="etapa !== 'form' || aberto !== {{ $servico->id }}"
                               placeholder="Só números ou com máscara">

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <button type="button" x-on:click="etapa = 'resumo'"
                                    class="hover:text-brand-600 dark:hover:text-brand-400 text-sm text-gray-500 dark:text-gray-400">
                                Voltar ao resumo
                            </button>

                            <x-avalia.botao tamanho="sm">
                                {{ $vendedor->ehAdmin() ? 'Consultar' : 'Consultar para demonstrar' }}
                            </x-avalia.botao>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
