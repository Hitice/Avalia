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

    $itensCategoria = collect(\App\Enums\Categoria::rotulos() + ['' => 'Todos'])
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
            Preco de venda por servico e faixa de consumo minimo. Editavel a qualquer momento;
            consulta e fatura guardam o preco do dia da emissao.
        </p>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'catalogo'])

    @include('paginas.catalogo.avisos')

    @if (! $catalogo)
        <div class="aviso aviso-alerta">
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

            <div class="overflow-hidden cartao">
                <div class="overflow-x-auto">
                    <table class="tabela min-w-[54rem]">
                        <thead class="tabela-cabecalho">
                            <tr>
                                {{-- text-left explicito: o navegador centraliza th por
                                     padrao, e o text-left da tabela nao vence isso. --}}
                                <th class="px-5 py-3 text-left font-medium">Servico</th>
                                @if ($visao === 'custo')
                                    {{-- Custo e um por servico: o fornecedor cobra por consulta,
                                         nao pelo pacote do cliente. --}}
                                    <th class="px-4 py-3 text-right font-medium whitespace-nowrap">Custo do fornecedor</th>
                                @else
                                @foreach ($faixas as $faixa)
                                    <th class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                        {{ $faixa === 0 ? 'Sem mínimo' : Dinheiro::brl($faixa) }}
                                    </th>
                                @endforeach
                                @endif
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
                                            <a href="{{ route('catalogo.servicos.editar', $linha['servico']) }}"
                                               class="hover:text-brand-500 dark:hover:text-brand-400 underline decoration-dotted">
                                                <code>{{ $linha['servico']->codigo }}</code>
                                            </a>
                                            @unless ($linha['servico']->ativo)
                                                <span class="etiqueta etiqueta-neutra">
                                                    inativo
                                                </span>
                                            @endunless
                                            @if ($linha['servico']->exige_liberacao)
                                                <span class="etiqueta etiqueta-alerta">
                                                    aguarda liberacao
                                                </span>
                                            @endif
                                        </span>
                                    </td>

                                    @if ($visao === 'custo')
                                        @php $primeiro = $linha['precos']->first(); @endphp
                                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            <input type="text" inputmode="decimal" placeholder="-"
                                                   name="custos[{{ $linha['servico']->id }}]"
                                                   value="{{ $primeiro?->custo_cents === null ? '' : Dinheiro::numero($primeiro->custo_cents) }}"
                                                   class="campo-celula">
                                        </td>
                                    @else
                                    @foreach ($faixas as $faixa)
                                        @php
                                            $preco = $linha['precos']->get($faixa);
                                            $prejuizo = $preco && $visao === 'margem'
                                                && Margem::daPrejuizo($preco->preco_cents, $preco->custo_cents, $catalogo->imposto_bps, $catalogo->comissaoBps());
                                        @endphp

                                        <td class="{{ $prejuizo ? 'bg-error-50 dark:bg-error-500/10' : '' }} px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            @if (! $preco)
                                                -
                                            @elseif ($visao === 'margem')
                                                @include('paginas.catalogo.celula-margem')
                                            @else
                                                @php
                                                    $valor = $visao === 'custo' ? $preco->custo_cents : $preco->preco_cents;
                                                @endphp
                                                <input type="text" inputmode="decimal"
                                                       name="{{ $campo }}[{{ $preco->id }}]"
                                                       value="{{ $valor === null ? '' : Dinheiro::numero($valor) }}"
                                                       @if ($visao === 'custo') placeholder="-" @endif
                                                       class="campo-celula">
                                            @endif
                                        </td>
                                    @endforeach
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($visao === 'custo' ? 1 : count($faixas)) + 1 }}" class="tabela-vazia">
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
                Custo cobrado pelo fornecedor. Campo em branco significa custo ainda nao cadastrado,
                nao e o mesmo que custo zero.
            @else
                Margem = venda − custo − imposto de {{ $catalogo->impostoRotulo() }} − comissao do vendedor. Amarelo e abaixo da margem alvo; vermelho
                e prejuizo: o servico nao paga nem fornecedor, imposto e comissao.
            @endif
        </p>
    @endif
@endsection
