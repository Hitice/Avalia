@extends('layouts.app', ['title' => 'Catalogo'])

@php
    use App\Http\Controllers\CatalogoController;
    use App\Support\Dinheiro;
    use App\Support\Margem;

    $editando = $visao !== 'margem';
    $campo = $visao === 'custo' ? 'custos' : 'precos';
    $acao = $visao === 'custo' ? 'catalogo.custos' : 'catalogo.precos';

    // Mantem o outro eixo do filtro ao trocar de aba.
    $comFiltro = fn (array $troca) => route('catalogo.tabela', array_filter(
        array_merge(['categoria' => $categoria, 'visao' => $visao], $troca),
    ));

    // Os dois filtros da matriz, montados juntos para ficarem lado a lado: sao
    // eixos do mesmo recorte, e empilhar cada um numa fileira dava tres faixas
    // de pilulas iguais sem hierarquia nenhuma.
    $itensVisao = collect(CatalogoController::VISOES)
        ->map(fn ($rotulo, $chave) => [
            'rotulo' => $rotulo,
            'url' => $comFiltro(['visao' => $chave === 'venda' ? null : $chave]),
        ])
        ->all();

    $itensCategoria = collect(\App\Models\Servico::CATEGORIAS + ['' => 'Todos'])
        ->map(fn ($rotulo, $chave) => [
            'rotulo' => $rotulo,
            'url' => $comFiltro(['categoria' => $chave ?: null]),
        ])
        ->all();
@endphp

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
        {{-- Os dois eixos do recorte na mesma linha: o que a matriz mostra e
             que servicos ela lista. Credito e Veicular vem antes de Todos
             porque o operador trabalha por bloco; a visao completa e a excecao. --}}
        <div class="mb-6 flex flex-wrap items-end gap-x-8 gap-y-4">
            <x-avalia.segmentado data-abas="visoes" rotulo="Mostrar" :atual="$visao" :itens="$itensVisao" />
            <x-avalia.segmentado data-abas="categorias" rotulo="Servicos" :atual="$categoria ?? ''" :itens="$itensCategoria" />
        </div>

        @if ($visao === 'venda')
            @include('paginas.catalogo.acoes-tabela')
        @endif

        @if ($visao === 'margem')
            @include('paginas.catalogo.acoes-imposto')
        @endif

        <form method="POST" action="{{ $editando ? route($acao, $catalogo) : '' }}">
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
                                        {{ $faixa === 0 ? 'Sem mínimo' : Dinheiro::brl($faixa) }}
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
                                            @unless ($linha['servico']->ativo)
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                                    inativo
                                                </span>
                                            @endunless
                                            @if ($linha['servico']->exige_liberacao)
                                                <span class="bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400 rounded-full px-2 py-0.5 font-medium">
                                                    aguarda liberacao
                                                </span>
                                            @endif
                                        </span>
                                    </td>

                                    @foreach ($faixas as $faixa)
                                        @php
                                            $preco = $linha['precos']->get($faixa);
                                            $prejuizo = $preco && $visao === 'margem'
                                                && Margem::daPrejuizo($preco->preco_cents, $preco->custo_cents, $catalogo->imposto_bps);
                                        @endphp

                                        <td class="{{ $prejuizo ? 'bg-error-50 dark:bg-error-500/10' : '' }} px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            @if (! $preco)
                                                —
                                            @elseif ($visao === 'margem')
                                                @include('paginas.catalogo.celula-margem')
                                            @else
                                                @php
                                                    $valor = $visao === 'custo' ? $preco->custo_cents : $preco->preco_cents;
                                                @endphp
                                                <input type="text" inputmode="decimal"
                                                       name="{{ $campo }}[{{ $preco->id }}]"
                                                       value="{{ $valor === null ? '' : Dinheiro::numero($valor) }}"
                                                       @if ($visao === 'custo') placeholder="—" @endif
                                                       class="focus:border-brand-500 w-24 rounded-lg border border-gray-300 bg-white px-2 py-1 text-right text-sm tabular-nums text-gray-800 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
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

            @if ($editando && $linhas->isNotEmpty())
                <x-avalia.botao class="mt-5">
                    {{ $visao === 'custo' ? 'Salvar custos' : 'Salvar precos' }}
                </x-avalia.botao>
            @endif
        </form>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            @if ($visao === 'venda')
                Precos de venda ao cliente. Custo do fornecedor e margem sao internos e nao aparecem
                para vendedor nem para cliente.
            @elseif ($visao === 'custo')
                Custo cobrado pelo fornecedor. Campo em branco significa custo ainda nao cadastrado —
                nao e o mesmo que custo zero.
            @else
                Margem = venda − custo − imposto de {{ $catalogo->impostoRotulo() }}. Celula em vermelho
                e venda abaixo do piso: o servico esta sendo vendido no prejuizo.
            @endif
        </p>
    @endif
@endsection
