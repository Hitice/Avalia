@extends('layouts.app', ['title' => 'Vendas QR'])

@php
    use App\Support\Dinheiro;


    // As linhas do negocio, na ordem em que o dinheiro sai. A classe da bolinha
    // vai literal: montada em tempo de execucao o Tailwind nao a gera, e a
    // legenda saiu sem cor durante semanas sem ninguem perceber.
    $linhas = [
        ['rotulo' => 'Receita', 'chave' => 'bruto', 'ponto' => 'ponto-serie-4', 'sinal' => ''],
        ['rotulo' => 'Custo das placas', 'chave' => 'custo', 'ponto' => 'ponto-serie-3', 'sinal' => '−'],
        ['rotulo' => 'Comissões', 'chave' => 'comissao', 'ponto' => 'ponto-serie-2', 'sinal' => '−'],
        ['rotulo' => 'Lucro', 'chave' => 'lucro', 'ponto' => 'ponto-serie-1', 'sinal' => ''],
    ];
@endphp

@section('content')
    <x-avalia.cabecalho-pagina titulo="Vendas QR">
        <form method="GET" action="{{ route('plaquinhas.vendas') }}" class="flex flex-wrap items-center gap-2">
            <label for="mes" class="sr-only">Mês</label>
            <select id="mes" name="mes" class="campo w-auto" onchange="this.form.submit()">
                @foreach ($meses as $opcao)
                    <option value="{{ $opcao->format('Y-m') }}" @selected($opcao->format('Y-m') === $mes->format('Y-m'))>
                        {{ $opcao->translatedFormat('F/Y') }}
                    </option>
                @endforeach
            </select>
        </form>
    </x-avalia.cabecalho-pagina>

    @include('parciais.avisos')

    @if (! empty($sociosAusentes))
        <div class="aviso aviso-erro mb-6">
            Sem conta na equipe: {{ implode(', ', $sociosAusentes) }}. O lucro está sendo dividido
            só entre quem foi encontrado.
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Placas no mês" :valor="$placas"
                                   :href="route('etiquetas.index')"
                                   :ajuda="$semVendedor > 0 ? $semVendedor.' sem vendedor' : null" />

        <x-avalia.cartao-indicador rotulo="Receita" :valor="Dinheiro::brl($totais['bruto'])" />

        <x-avalia.cartao-indicador rotulo="Comissões" :valor="Dinheiro::brl($totais['comissao'])" />

        <x-avalia.cartao-indicador rotulo="Lucro" :valor="Dinheiro::brl($totais['lucro'])"
                                   tom="text-brand-600 dark:text-brand-400" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
        <div class="cartao p-6">
            <h2 class="titulo-cartao">Vendas por dia</h2>

            <x-avalia.grafico-vendas-dia :por-dia="$porDia" :mes="$mes" />
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="titulo-cartao">Caixa</h2>
            </div>

            <table class="tabela">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="tabela-th text-left"><span class="sr-only">Linha</span></th>
                    <th scope="col" class="tabela-th text-right">Mês</th>
                    <th scope="col" class="tabela-th text-right">Total</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($linhas as $linha)
                        @php $ehLucro = $linha['chave'] === 'lucro'; @endphp
                        <tr @class(['bg-gray-50/60 dark:bg-gray-800/40' => $ehLucro])>
                            <td class="tabela-td">
                                <span class="flex items-center gap-2 {{ $ehLucro ? 'font-medium text-gray-800 dark:text-white/90' : 'text-gray-600 dark:text-gray-300' }}">
                                    <span class="{{ $linha['ponto'] }} size-2.5 shrink-0 rounded-full"></span>
                                    {{ $linha['rotulo'] }}
                                </span>
                            </td>
                            <td class="tabela-td text-right tabular-nums {{ $ehLucro ? 'font-semibold text-gray-800 dark:text-white/90' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ $linha['sinal'] }}{{ Dinheiro::brl($totais[$linha['chave']]) }}
                            </td>
                            <td class="tabela-td text-right tabular-nums {{ $ehLucro ? 'font-semibold text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $linha['sinal'] }}{{ Dinheiro::brl($total[$linha['chave']]) }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- O split fica DENTRO do caixa: a parte de cada socio nao e
                         outro assunto, e o mesmo dinheiro com dono. Em cartao
                         separado, a soma das duas partes e o lucro apareciam
                         longe uma da outra e ninguem conferia. --}}
                    <tr class="border-t-2 border-gray-200 dark:border-gray-700">
                        <td colspan="3" class="px-5 pt-4 pb-1">
                            <span class="rotulo-grupo">Split</span>
                        </td>
                    </tr>

                    {{-- O numero grande e o pro-labore, que sai na sexta. O retido
                         ja esta no caixa desde a venda; nao e dinheiro a pagar. --}}
                    @forelse ($porSocio as $socio)
                        <tr>
                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                {{ $socio['nome'] }}
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    retido na empresa {{ Dinheiro::brl($socio['retido']) }}
                                </span>
                            </td>
                            <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($socio['prolabore']) }}
                            </td>
                            <td class="tabela-td text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ Dinheiro::brl($socio['prolaboreTotal']) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="tabela-vazia">Nenhum sócio configurado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_1.35fr]">
        <div class="cartao p-6">
            <h2 class="titulo-cartao">Por vendedor</h2>

            @php $maiorVendedor = max(1, $porVendedor->max('placas') ?? 0); @endphp

            @forelse ($porVendedor as $v)
                <div class="mt-5 first:mt-6">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-gray-800 dark:text-white/90">
                            {{ $v['nome'] }}
                            @if ($v['eh_socio'])
                                <span class="etiqueta etiqueta-neutra ml-1">sócio</span>
                            @endif
                        </span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $v['placas'] }} · {{ $v['eh_socio'] ? Dinheiro::brl($v['bruto']) : Dinheiro::brl($v['comissao']) }}
                        </span>
                    </div>

                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="ponto-serie-1 h-full rounded-full"
                             style="width: {{ round($v['placas'] / $maiorVendedor * 100, 2) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="tabela-vazia mt-6">Nenhuma placa vendida neste mês.</p>
            @endforelse

            {{-- O que a sexta paga. Desde sempre, e nao do mes: comissao que
                 ficou de um mes para o outro continua devida. --}}
            <h3 class="rotulo-grupo mt-8">Comissões a pagar</h3>

            @forelse ($aPagar as $divida)
                <form method="POST" action="{{ route('plaquinhas.comissao.pagar', $divida['id']) }}"
                      class="mt-3 flex items-center justify-between gap-3 text-sm">
                    @csrf
                    <span class="text-gray-800 dark:text-white/90">
                        {{ $divida['nome'] }}
                        <span class="text-gray-500 dark:text-gray-400">· {{ $divida['placas'] }} {{ $divida['placas'] === 1 ? 'placa' : 'placas' }}</span>
                    </span>
                    <button type="submit" class="botao botao-primario botao-sm"
                            onclick="return confirm('Marcar {{ Dinheiro::brl($divida['cents']) }} como pagos a {{ $divida['nome'] }}?')">
                        Pagar {{ Dinheiro::brl($divida['cents']) }}
                    </button>
                </form>
            @empty
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Nenhuma comissão em aberto.</p>
            @endforelse

            {{-- O que ja saiu, com dia e hora: e a prova da sexta. --}}
            <h3 class="rotulo-grupo mt-8">Comissões pagas</h3>

            @forelse ($pagas as $lote)
                <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-800 dark:text-white/90">
                        {{ $lote['nome'] }}
                        <span class="text-gray-500 dark:text-gray-400">· {{ $lote['placas'] }} {{ $lote['placas'] === 1 ? 'placa' : 'placas' }}</span>
                    </span>
                    <span class="shrink-0 tabular-nums text-gray-600 dark:text-gray-300">
                        {{ $lote['quando']->translatedFormat('D d/m H:i') }} · <span class="font-medium text-gray-800 dark:text-white/90">{{ Dinheiro::brl($lote['cents']) }}</span>
                    </span>
                </div>
            @empty
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Nenhuma comissão paga ainda.</p>
            @endforelse
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="titulo-cartao">Vendas do mês</h2>
            </div>
            <div class="tabela-rolagem">
                <table class="tabela min-w-[32rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="tabela-th text-left">Código</th>
                        <th scope="col" class="tabela-th text-left">Cliente</th>
                        <th scope="col" class="tabela-th text-left">Vendedor</th>
                        <th scope="col" class="tabela-th text-right">Valor</th>
                        <th scope="col" class="tabela-th text-right">Data</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($vendas as $venda)
                            <tr>
                                <td class="tabela-td">
                                    <a href="{{ route('etiquetas.ficha', $venda) }}"
                                       class="font-mono text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $venda->codigo }}
                                    </a>
                                </td>
                                <td class="tabela-td text-gray-800 dark:text-white/90">{{ $venda->cliente_nome ?? '—' }}</td>
                                <td class="tabela-td text-gray-600 dark:text-gray-300">
                                    {{ $venda->vendedor?->nome ?? 'Não identificado' }}
                                </td>
                                <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl((int) $venda->valor_cents) }}
                                </td>
                                <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ $venda->vendida_em->format('d/m H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="tabela-vazia">Nenhuma placa vendida neste mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
