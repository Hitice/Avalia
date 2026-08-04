@extends('layouts.app', ['title' => 'Catalogo'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Catalogo</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Preco de venda por servico e faixa de consumo minimo. Editavel a qualquer momento —
            consulta e fatura guardam o preco do dia da emissao.
        </p>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'catalogo'])

    @include('paginas.catalogo.avisos')

    @if (! $catalogo)
        <div class="border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400 rounded-lg border p-4 text-sm">
            Catalogo vazio. Rode <code>php artisan db:seed --class=CatalogoSeeder</code> para importar
            a tabela de referencia.
        </div>
    @else
        @include('paginas.catalogo.acoes-tabela')

        {{-- Credito, Veicular e depois Todos: o operador trabalha por bloco de
             servico, e a visao completa e a excecao. --}}
        <div data-abas="categorias" class="mb-4 flex flex-wrap gap-2">
            @php
                $abasCategoria = \App\Models\Servico::CATEGORIAS + ['' => 'Todos'];
            @endphp

            @foreach ($abasCategoria as $chave => $rotulo)
                <a href="{{ route('catalogo.tabela') }}{{ $chave ? '?categoria='.$chave : '' }}"
                   class="{{ ($categoria ?? '') === $chave
                       ? 'bg-brand-500 text-white'
                       : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]' }} rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-800">
                    {{ $rotulo }}
                </a>
            @endforeach
        </div>

        {{-- A tabela inteira e um formulario: o operador ajusta quantas celulas
             quiser e grava tudo de uma vez. --}}
        <form method="POST" action="{{ route('catalogo.precos', $catalogo) }}">
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
                                    <td class="px-5 py-3 text-left">
                                        <span class="block font-medium text-gray-800 dark:text-white/90">
                                            {{ $linha['servico']->nome }}
                                        </span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <code>{{ $linha['servico']->codigo }}</code>
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
                                            @if ($preco)
                                                <input type="text" inputmode="decimal"
                                                       name="precos[{{ $preco->id }}]"
                                                       value="{{ \App\Support\Dinheiro::numero($preco->preco_cents) }}"
                                                       class="focus:border-brand-500 w-24 rounded-lg border border-gray-300 bg-white px-2 py-1 text-right text-sm tabular-nums text-gray-800 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            @else
                                                —
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

            @if ($linhas->isNotEmpty())
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 mt-5 rounded-lg px-4 py-2.5 text-sm font-medium text-white">
                    Salvar precos
                </button>
            @endif
        </form>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Precos de venda ao cliente. Custo do fornecedor e margem sao internos e nao aparecem
            para vendedor nem para cliente.
        </p>
    @endif
@endsection
