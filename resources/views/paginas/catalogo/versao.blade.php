@extends('layouts.app', ['title' => $versao->rotulo])

@section('content')
    <div class="mb-6">
        <a href="{{ route('catalogo.versoes.index') }}"
           class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Catalogo
        </a>

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $versao->rotulo }}</h1>
            <x-catalogo.situacao :versao="$versao" />
        </div>

        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ count($linhas) }} servicos, {{ count($faixas) }} faixas de consumo minimo.
            @if ($versao->estaCongelada())
                Congelada em {{ $versao->congelada_em?->format('d/m/Y H:i') ?? '—' }}.
            @else
                Em rascunho: ainda aceita alteracao de preco.
            @endif
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    @include('paginas.catalogo.acoes-versao')

    {{-- Filtro por categoria. Sem parametro = tudo. --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @php
            $abas = ['' => 'Todos'] + \App\Models\Servico::CATEGORIAS;
        @endphp

        @foreach ($abas as $chave => $rotulo)
            <a href="{{ route('catalogo.versoes.mostrar', $versao) }}{{ $chave ? '?categoria='.$chave : '' }}"
               class="{{ ($categoria ?? '') === $chave
                   ? 'bg-brand-500 text-white'
                   : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]' }} rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium dark:border-gray-800">
                {{ $rotulo }}
            </a>
        @endforeach
    </div>

    {{-- Em rascunho a tabela inteira e um formulario: o operador ajusta
         quantas celulas quiser e grava tudo de uma vez. --}}
    <form method="POST" action="{{ route('catalogo.versoes.precos', $versao) }}">
        @csrf
        @method('PUT')

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[54rem] text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        {{-- text-left explicito: o navegador centraliza th por
                             padrao, e o text-left da tabela nao vence isso. --}}
                        <th class="px-5 py-3 text-left font-medium">Servico</th>
                        @foreach ($faixas as $faixa)
                            <th class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                {{ $faixa === 0 ? 'Sem mínimo' : \App\Support\Dinheiro::brl($faixa) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($linhas as $linha)
                        <tr>
                            <td class="px-5 py-3">
                                <span class="block font-medium text-gray-800 dark:text-white/90">
                                    {{ $linha['servico']->nome }}
                                </span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <code>{{ $linha['servico']->codigo }}</code>
                                    <span>{{ $linha['servico']->rotuloCategoria() }}</span>
                                    @if ($linha['servico']->exige_liberacao)
                                        <span class="bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400 rounded-full px-2 py-0.5 font-medium">
                                            aguarda liberacao
                                        </span>
                                    @endif
                                </span>
                            </td>

                            @foreach ($faixas as $faixa)
                                @php $preco = $linha['precos']->get($faixa); @endphp
                                <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    @if ($preco && $versao->podeEditar())
                                        <input type="text" inputmode="decimal"
                                               name="precos[{{ $preco->id }}]"
                                               value="{{ \App\Support\Dinheiro::numero($preco->preco_cents) }}"
                                               class="focus:border-brand-500 w-24 rounded-lg border border-gray-300 bg-white px-2 py-1 text-right text-sm tabular-nums text-gray-800 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    @else
                                        {{ $preco?->preco ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($faixas) + 1 }}" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum servico nesta categoria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

        @if ($versao->podeEditar() && $linhas->isNotEmpty())
            <button type="submit" class="bg-brand-500 hover:bg-brand-600 mt-5 rounded-lg px-4 py-2.5 text-sm font-medium text-white">
                Salvar precos
            </button>
        @endif
    </form>

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        Precos de venda ao cliente. Custo do fornecedor e margem sao internos e nao aparecem
        para vendedor nem para cliente.
    </p>
@endsection
