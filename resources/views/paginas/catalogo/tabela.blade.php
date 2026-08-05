@extends('layouts.app', ['title' => 'Catalogo'])

@php
    use App\Http\Controllers\CatalogoController;
    use App\Support\Dinheiro;
    use App\Support\Margem;

    // Mantem o outro eixo do filtro ao trocar de aba.
    $comFiltro = fn (array $troca) => route('catalogo.tabela', array_filter(
        array_merge(['categoria' => $categoria, 'visao' => $visao], $troca),
    ));

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
    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">Catalogo</h1>

    @include('paginas.catalogo.abas', ['atual' => 'catalogo'])

    @include('paginas.catalogo.avisos')

    @if (! $catalogo)
        <div class="aviso aviso-alerta">
            Catalogo vazio. Rode <code>php artisan db:seed --class=CatalogoSeeder</code> para importar
            a tabela de referencia.
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div class="flex flex-wrap items-end gap-x-8 gap-y-4">
                <x-avalia.segmentado data-abas="visoes" rotulo="Mostrar" :atual="$visao" :itens="$itensVisao" />
                <x-avalia.segmentado data-abas="categorias" rotulo="Servicos" :atual="$categoria ?? ''" :itens="$itensCategoria" />
            </div>

            @if ($visao === 'margem')
                <a href="{{ route('catalogo.parametros') }}"
                   class="hover:text-brand-500 dark:hover:text-brand-400 text-xs text-gray-500 dark:text-gray-400">
                    Imposto {{ $catalogo->impostoRotulo() }} · comissao {{ number_format($catalogo->comissaoBps() / 100, 0) }}%
                    · alvo {{ $catalogo->margemAlvoRotulo() }} · ajustar
                </a>
            @endif
        </div>

        <div class="cartao overflow-hidden">
            <div class="tabela-rolagem">
                <table class="tabela min-w-[54rem]">
                    <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                        <tr>
                            <th class="tabela-th text-left">Servico</th>

                            @if ($visao === 'custo')
                                {{-- Um custo por servico: o fornecedor cobra por consulta,
                                     nao pelo pacote que o cliente contratou. --}}
                                <th class="px-4 py-3 text-right font-medium whitespace-nowrap">Custo</th>
                            @else
                                @foreach ($faixas as $faixa)
                                    <th class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                        {{ Dinheiro::faixa($faixa) }}
                                    </th>
                                @endforeach
                            @endif

                            <th class="px-5 py-3"><span class="sr-only">Acoes</span></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($linhas as $linha)
                            @php $servico = $linha['servico']; @endphp

                            <tr>
                                <td class="tabela-td text-left">
                                    <span class="flex items-center gap-1.5 font-medium text-gray-800 dark:text-white/90">
                                        {{ $servico->nome }}
                                        @if ($servico->suprimido())
                                            <x-avalia.cadeado titulo="Veicular: numeros suprimidos ate o contrato com o fornecedor" />
                                        @endif
                                    </span>
                                    <span class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <code>{{ $servico->codigo }}</code>
                                        @unless ($servico->ativo)
                                            <span class="etiqueta etiqueta-neutra">pausado</span>
                                        @endunless
                                        @if ($servico->exige_liberacao)
                                            <span class="etiqueta etiqueta-alerta">aguarda liberacao</span>
                                        @endif
                                    </span>
                                </td>

                                @if ($servico->suprimido())
                                    {{-- Preco, custo e margem de veicular sao estimativa enquanto o
                                         contrato com o fornecedor nao fecha. Numero exibido sem aviso
                                         vira proposta, entao a linha aparece sem os valores. --}}
                                    <td colspan="{{ $visao === 'custo' ? 1 : count($faixas) }}"
                                        class="px-4 py-3 text-right text-xs valor-suprimido">
                                        suprimido
                                    </td>
                                @elseif ($visao === 'custo')
                                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                        {{ $linha['precos']->first()?->custo_cents === null
                                            ? '-'
                                            : Dinheiro::brl($linha['precos']->first()->custo_cents) }}
                                    </td>
                                @else
                                    @foreach ($faixas as $faixa)
                                        @php $preco = $linha['precos']->get($faixa); @endphp

                                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            @if (! $preco)
                                                -
                                            @elseif ($visao === 'margem')
                                                @php
                                                    $pct = Margem::pct($preco->preco_cents, $preco->custo_cents,
                                                        $catalogo->imposto_bps, $catalogo->comissaoBps());
                                                    $abaixo = ! Margem::atinge($preco->preco_cents, $preco->custo_cents,
                                                        $catalogo->imposto_bps, $catalogo->comissaoBps(), $catalogo->margem_alvo_bps);
                                                @endphp

                                                @if ($pct === null)
                                                    <span class="text-gray-400 dark:text-gray-600">-</span>
                                                @else
                                                    <span class="{{ $pct < 0
                                                        ? 'text-error-600 dark:text-error-400 font-medium'
                                                        : ($abaixo ? 'text-warning-600 dark:text-warning-400' : '') }}">
                                                        {{ number_format($pct, 1, ',', '.') }}%
                                                    </span>
                                                @endif
                                            @else
                                                {{ Dinheiro::brl($preco->preco_cents) }}
                                            @endif
                                        </td>
                                    @endforeach
                                @endif

                                <td class="px-5 py-3 text-right">
                                    <x-avalia.botao variante="secundario" tamanho="sm"
                                                    :href="route('catalogo.servicos.editar', $servico)">Editar</x-avalia.botao>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($visao === 'custo' ? 1 : count($faixas)) + 2 }}" class="tabela-vazia">
                                    Nenhum servico nesta categoria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
