@extends('layouts.app', ['title' => 'Vendas QR'])

@php
    use App\Support\Dinheiro;

    $maior = max(1, $porDia->max('placas'));

    // Geometria em PHP, e nao em JavaScript: o servidor ja tem os numeros, e
    // desenhar no cliente faria a tela aparecer vazia para preencher depois.
    $g = ['w' => 760, 'h' => 170, 'pb' => 26, 'pt' => 14];
    $g['util'] = $g['h'] - $g['pb'] - $g['pt'];
    $g['base'] = $g['pt'] + $g['util'];
    $passo = $g['w'] / max(1, $porDia->count());
    $barra = max(3, min(18, $passo * 0.62));

    // As linhas do negocio, na ordem em que o dinheiro sai.
    $linhas = [
        ['rotulo' => 'Receita', 'chave' => 'bruto', 'serie' => null, 'sinal' => ''],
        ['rotulo' => 'Custo das placas', 'chave' => 'custo', 'serie' => 3, 'sinal' => '−'],
        ['rotulo' => 'Comissões', 'chave' => 'comissao', 'serie' => 2, 'sinal' => '−'],
        ['rotulo' => 'Lucro', 'chave' => 'lucro', 'serie' => 1, 'sinal' => ''],
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Vendas QR</h1>

        <form method="GET" action="{{ route('plaquinhas.vendas') }}" class="flex flex-wrap items-center gap-2">
            <label for="mes" class="sr-only">Mês</label>
            <select id="mes" name="mes" class="campo w-auto py-2" onchange="this.form.submit()">
                @foreach ($meses as $opcao)
                    <option value="{{ $opcao->format('Y-m') }}" @selected($opcao->format('Y-m') === $mes->format('Y-m'))>
                        {{ $opcao->translatedFormat('F/Y') }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

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
            <h2 class="font-medium text-gray-800 dark:text-white/90">Vendas por dia</h2>

            @if ($totais['bruto'] === 0)
                <p class="tabela-vazia mt-6">Sem vendas no mês.</p>
            @else
                <svg viewBox="0 0 {{ $g['w'] }} {{ $g['h'] }}" class="mt-5 w-full" role="img"
                     aria-label="Placas vendidas por dia em {{ $mes->translatedFormat('F/Y') }}">
                    @foreach ([0, 0.5, 1] as $fracao)
                        @php $y = $g['pt'] + $g['util'] * (1 - $fracao); @endphp
                        <line x1="0" y1="{{ $y }}" x2="{{ $g['w'] }}" y2="{{ $y }}"
                              class="grade-grafico" stroke-width="1"
                              stroke-dasharray="{{ $fracao === 0 ? 'none' : '3 3' }}" />
                    @endforeach

                    @foreach ($porDia as $i => $d)
                        @php
                            $altura = $d['placas'] / $maior * $g['util'];
                            $x = $i * $passo + ($passo - $barra) / 2;
                            $r = min(2, $barra / 2, max(0.01, $altura));
                        @endphp

                        @if ($d['placas'] > 0)
                            <path class="serie-1"
                                  d="M {{ $x }} {{ $g['base'] }} V {{ $g['base'] - $altura + $r }} Q {{ $x }} {{ $g['base'] - $altura }} {{ $x + $r }} {{ $g['base'] - $altura }} H {{ $x + $barra - $r }} Q {{ $x + $barra }} {{ $g['base'] - $altura }} {{ $x + $barra }} {{ $g['base'] - $altura + $r }} V {{ $g['base'] }} Z">
                                <title>{{ $d['rotulo'] }}: {{ $d['placas'] }} {{ $d['placas'] === 1 ? 'placa' : 'placas' }} · {{ Dinheiro::brl($d['bruto']) }}</title>
                            </path>
                        @endif

                        {{-- Rotulo a cada cinco dias. Trinta numeros lado a lado
                             se sobrepoem e nenhum fica legivel. --}}
                        @if ($d['dia'] === 1 || $d['dia'] % 5 === 0)
                            <text x="{{ $i * $passo + $passo / 2 }}" y="{{ $g['h'] - 8 }}"
                                  text-anchor="middle" class="fill-gray-400 text-[10px] tabular-nums dark:fill-gray-500">
                                {{ $d['dia'] }}
                            </text>
                        @endif
                    @endforeach
                </svg>
            @endif
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Caixa</h2>
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
                                    @if ($linha['serie'])
                                        <span class="ponto-serie-{{ $linha['serie'] }} size-2.5 shrink-0 rounded-full"></span>
                                    @else
                                        <span class="size-2.5 shrink-0"></span>
                                    @endif
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

                    @forelse ($porSocio as $socio)
                        <tr>
                            <td class="tabela-td text-gray-600 dark:text-gray-300">{{ $socio['nome'] }}</td>
                            <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($socio['mes']) }}
                            </td>
                            <td class="tabela-td text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ Dinheiro::brl($socio['total']) }}
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
            <h2 class="font-medium text-gray-800 dark:text-white/90">Por vendedor</h2>

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
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Vendas do mês</h2>
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
                                    {{ $venda->vendida_em->format('d/m') }}
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
