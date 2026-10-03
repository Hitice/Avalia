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

    {{-- O ritmo do mes contra a meta, na largura inteira. --}}
    <div class="cartao mb-6 p-6">
            <h2 class="titulo-cartao">Vendas por dia</h2>

            <x-avalia.grafico-vendas-dia :por-dia="$porDia" :mes="$mes" :meta="$meta" />
        </div>

    {{-- As vendas do mes numa fila que rola de lado: quatro por vez, uma
         linha por venda. Lista inteira para quem confere e cada card leva a ficha. --}}
    <div class="mb-6 cartao p-5">
        <div class="flex items-baseline justify-between">
            <h2 class="titulo-cartao">Vendas do mês</h2>
            <span class="subtitulo-pagina mt-0">{{ $vendas->count() }} {{ $vendas->count() === 1 ? 'venda' : 'vendas' }}</span>
        </div>

        @if ($vendas->isEmpty())
            <p class="tabela-vazia mt-4">Nenhuma placa vendida neste mês.</p>
        @else
            <div class="-mx-5 mt-4 flex snap-x gap-3 overflow-x-auto px-5 pb-2">
                @foreach ($vendas as $venda)
                    <a href="{{ route('etiquetas.ficha', $venda) }}"
                       class="flutuante flex w-[16rem] shrink-0 snap-start items-center justify-between gap-3 px-4 py-3 text-sm transition hover:border-brand-300">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-gray-800 dark:text-white/90">{{ $venda->cliente_nome ?? 'Sem cliente' }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-mono">{{ $venda->codigo }}</span> · {{ $venda->vendedor?->nome ?? 'Não identificado' }} · {{ $venda->vendida_em->format('d/m H:i') }}
                            </span>
                        </span>
                        <span class="shrink-0 tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl((int) $venda->valor_cents) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    {{-- Duas colunas: o dinheiro da casa, e a equipe numa tabela so: quem
         vendeu, quanto, a comissao do mes e o que esta em aberto, com o botao
         de pagar na propria linha. --}}
    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
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

                    {{-- O numero grande e o pro-labore, pago pela tela de Pagamentos. O retido
                         ja esta no caixa desde a venda; nao e dinheiro a pagar. --}}
                    @forelse ($porSocio as $socio)
                        <tr>
                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                {{ $socio['nome'] }}
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    reinvestimento {{ Dinheiro::brl($socio['retido']) }}
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

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="titulo-cartao">Equipe</h2>
            </div>
            <div class="tabela-rolagem">
                <table class="tabela min-w-[36rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="tabela-th text-left">Vendedor</th>
                        <th scope="col" class="tabela-th text-right">Placas</th>
                        <th scope="col" class="tabela-th text-right">Bruto</th>
                        <th scope="col" class="tabela-th text-right">Comissão</th>
                        <th scope="col" class="tabela-th text-right">A pagar</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php $aPagarPorId = $aPagar->keyBy('id'); $listados = collect(); @endphp
                        @forelse ($porVendedor as $v)
                            @php $listados->push($v['id']); $divida = $aPagarPorId->get($v['id']); @endphp
                            <tr>
                                <td class="tabela-td text-gray-800 dark:text-white/90">
                                    {{ $v['nome'] }}
                                    @if ($v['eh_socio'])<span class="etiqueta etiqueta-neutra ml-1">sócio</span>@endif
                                </td>
                                <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $v['placas'] }}</td>
                                <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">{{ Dinheiro::brl($v['bruto']) }}</td>
                                <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">{{ $v['eh_socio'] ? '-' : Dinheiro::brl($v['comissao']) }}</td>
                                <td class="tabela-td text-right">
                                    @if ($divida)
                                        <form method="POST" action="{{ route('plaquinhas.comissao.pagar', $divida['id']) }}"
                                              onsubmit="return confirm('Marcar {{ Dinheiro::brl($divida['cents']) }} como pagos a {{ $divida['nome'] }}?')">
                                            @csrf
                                            <x-avalia.botao tamanho="sm">Pagar {{ Dinheiro::brl($divida['cents']) }}</x-avalia.botao>
                                        </form>
                                    @elseif (! $v['eh_socio'])
                                        <span class="etiqueta etiqueta-sucesso">em dia</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="tabela-vazia">Nenhuma placa vendida neste mês.</td></tr>
                        @endforelse

                        {{-- Comissao de mes anterior ainda em aberto, de quem nao vendeu neste. --}}
                        @foreach ($aPagar->reject(fn ($d) => $listados->contains($d['id'])) as $divida)
                            <tr>
                                <td class="tabela-td text-gray-800 dark:text-white/90">{{ $divida['nome'] }} <span class="ajuda-campo">de meses anteriores</span></td>
                                <td class="tabela-td text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $divida['placas'] }}</td>
                                <td class="tabela-td"></td>
                                <td class="tabela-td"></td>
                                <td class="tabela-td text-right">
                                    <form method="POST" action="{{ route('plaquinhas.comissao.pagar', $divida['id']) }}"
                                          onsubmit="return confirm('Marcar {{ Dinheiro::brl($divida['cents']) }} como pagos a {{ $divida['nome'] }}?')">
                                        @csrf
                                        <x-avalia.botao tamanho="sm">Pagar {{ Dinheiro::brl($divida['cents']) }}</x-avalia.botao>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="rotulo-grupo">Pagas</h3>
                @forelse ($pagas as $lote)
                    <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-800 dark:text-white/90">{{ $lote['nome'] }} <span class="text-gray-500 dark:text-gray-400">· {{ $lote['placas'] }} {{ $lote['placas'] === 1 ? 'placa' : 'placas' }}</span></span>
                        <span class="shrink-0 tabular-nums text-gray-600 dark:text-gray-300">{{ $lote['quando']->translatedFormat('D d/m H:i') }} · <span class="font-medium text-gray-800 dark:text-white/90">{{ Dinheiro::brl($lote['cents']) }}</span></span>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nenhuma comissão paga ainda.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
