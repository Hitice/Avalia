@extends('layouts.app', ['title' => 'Vendas QR'])

@php
    use App\Support\Dinheiro;

    $maior = max(1, $serie->max('placas'));

    // Geometria do grafico de barras. Em PHP e nao em JavaScript: sao doze
    // valores que o servidor ja tem, e desenhar no cliente significaria a tela
    // aparecer vazia e preencher depois.
    $g = ['w' => 760, 'h' => 200, 'pb' => 30, 'pt' => 10];
    $g['util'] = $g['h'] - $g['pb'] - $g['pt'];
    $passo = $g['w'] / max(1, $serie->count());
    $barra = min(34, $passo * 0.56);

    // Barra com as duas pontas de cima arredondadas, apoiada na linha de base.
    // `rx` no rect arredondaria tambem embaixo, e a barra descolaria do eixo.
    $topoRedondo = function (float $x, float $y, float $w, float $base, float $r = 4): string {
        $r = min($r, $w / 2, max(0.01, $base - $y));

        return "M {$x} {$base} V ".($y + $r)." Q {$x} {$y} ".($x + $r)." {$y}"
            .' H '.($x + $w - $r).' Q '.($x + $w)." {$y} ".($x + $w)." ".($y + $r)
            ." V {$base} Z";
    };

    // As tres partes de cada real, na ordem em que o dinheiro sai.
    $partes = [
        ['rotulo' => 'Custo', 'cents' => $totais['custo'], 'serie' => 3],
        ['rotulo' => 'Comissões', 'cents' => $totais['comissao'], 'serie' => 2],
        ['rotulo' => 'Sobra', 'cents' => $totais['sobra'], 'serie' => 1],
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Vendas QR</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $mes->translatedFormat('F \d\e Y') }}</p>
        </div>

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
            Sem conta na equipe: {{ implode(', ', $sociosAusentes) }}. A sobra está sendo dividida
            só entre quem foi encontrado.
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Placas vendidas" :valor="$placas"
                                   :href="route('etiquetas.index')"
                                   :ajuda="$semVendedor > 0 ? $semVendedor.' sem vendedor' : null" />

        <x-avalia.cartao-indicador rotulo="Faturamento" :valor="Dinheiro::brl($totais['bruto'])" />

        <x-avalia.cartao-indicador rotulo="Comissões" :valor="Dinheiro::brl($totais['comissao'])" />

        <x-avalia.cartao-indicador rotulo="Sobra a dividir" :valor="Dinheiro::brl($totais['sobra'])"
                                   tom="text-brand-600 dark:text-brand-400" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="cartao p-6">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Placas vendidas por mês</h2>

            @if ($serie->sum('placas') === 0)
                <p class="tabela-vazia mt-6">Nenhuma venda nos últimos doze meses.</p>
            @else
                <svg viewBox="0 0 {{ $g['w'] }} {{ $g['h'] }}" class="mt-6 w-full" role="img"
                     aria-label="Placas vendidas por mês nos últimos doze meses">
                    {{-- Tres linhas de grade, e nao uma por valor: a grade situa
                         a altura e para ai. --}}
                    @foreach ([0, 0.5, 1] as $fracao)
                        @php $y = $g['pt'] + $g['util'] * (1 - $fracao); @endphp
                        <line x1="0" y1="{{ $y }}" x2="{{ $g['w'] }}" y2="{{ $y }}"
                              class="grade-grafico" stroke-width="1"
                              stroke-dasharray="{{ $fracao === 0 ? 'none' : '3 3' }}" />
                    @endforeach

                    @foreach ($serie as $i => $ponto)
                        @php
                            $altura = $maior > 0 ? $ponto['placas'] / $maior * $g['util'] : 0;
                            $x = $i * $passo + ($passo - $barra) / 2;
                            $base = $g['pt'] + $g['util'];
                            $ehMaior = $ponto['placas'] === $maior && $ponto['placas'] > 0;
                        @endphp

                        @if ($ponto['placas'] > 0)
                            <path d="{{ $topoRedondo($x, $base - $altura, $barra, $base) }}" class="serie-1">
                                <title>{{ $ponto['rotulo'] }}: {{ $ponto['placas'] }} {{ $ponto['placas'] === 1 ? 'placa' : 'placas' }} · {{ Dinheiro::brl($ponto['bruto']) }}</title>
                            </path>

                            {{-- Numero so no pico. Um em cada barra viraria uma
                                 tabela desenhada, e o resto esta no passar o
                                 mouse. --}}
                            @if ($ehMaior)
                                <text x="{{ $x + $barra / 2 }}" y="{{ $base - $altura - 4 }}"
                                      text-anchor="middle" class="fill-gray-500 text-[11px] tabular-nums dark:fill-gray-400">
                                    {{ $ponto['placas'] }}
                                </text>
                            @endif
                        @endif

                        <text x="{{ $i * $passo + $passo / 2 }}" y="{{ $g['h'] - 10 }}"
                              text-anchor="middle" class="fill-gray-400 text-[11px] dark:fill-gray-500">
                            {{ $ponto['rotulo'] }}
                        </text>
                    @endforeach
                </svg>
            @endif
        </div>

        <div class="cartao p-6">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Para onde vai o faturamento</h2>

            @if ($totais['bruto'] === 0)
                <p class="tabela-vazia mt-6">Sem vendas no mês.</p>
            @else
                {{-- Barra empilhada com folga de 2px entre as partes: sem a
                     folga, duas cores vizinhas viram uma faixa continua e a
                     divisao desaparece. --}}
                <div class="mt-6 flex h-4 gap-0.5 overflow-hidden rounded-full">
                    @foreach ($partes as $parte)
                        @if ($parte['cents'] > 0)
                            <div class="ponto-serie-{{ $parte['serie'] }} h-full"
                                 style="width: {{ round($parte['cents'] / $totais['bruto'] * 100, 2) }}%"
                                 title="{{ $parte['rotulo'] }}: {{ Dinheiro::brl($parte['cents']) }}"></div>
                        @endif
                    @endforeach
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    @foreach ($partes as $parte)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                <span class="ponto-serie-{{ $parte['serie'] }} size-2.5 shrink-0 rounded-full"></span>
                                {{ $parte['rotulo'] }}
                            </dt>
                            <dd class="tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($parte['cents']) }}
                                <span class="ml-1 text-xs text-gray-400 dark:text-gray-500">
                                    {{ round($parte['cents'] / $totais['bruto'] * 100) }}%
                                </span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="cartao p-6">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Por vendedor</h2>

            @php $maiorVendedor = max(1, $porVendedor->max('placas') ?? 0); @endphp

            @forelse ($porVendedor as $linha)
                <div class="mt-5 first:mt-6">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-gray-800 dark:text-white/90">
                            {{ $linha['nome'] }}
                            @if ($linha['eh_socio'])
                                <span class="etiqueta etiqueta-neutra ml-1">sócio</span>
                            @endif
                        </span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $linha['placas'] }} · {{ Dinheiro::brl($linha['bruto']) }}
                        </span>
                    </div>

                    <div class="mt-2 flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="ponto-serie-1 h-full rounded-full"
                                 style="width: {{ round($linha['placas'] / $maiorVendedor * 100, 2) }}%"></div>
                        </div>
                        <span class="w-20 text-right text-sm tabular-nums text-gray-800 dark:text-white/90">
                            {{ $linha['eh_socio'] ? '—' : Dinheiro::brl($linha['comissao']) }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="tabela-vazia mt-6">Nenhuma placa vendida neste mês.</p>
            @endforelse

            @if ($porVendedor->isNotEmpty())
                <p class="ajuda-campo mt-5">Barra: placas. À direita: comissão.</p>
            @endif
        </div>

        <div class="cartao p-6">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Divisão entre sócios</h2>

            <div class="mt-6 space-y-3">
                @forelse ($porSocio as $socio)
                    <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800/50">
                        <div>
                            <span class="block text-sm text-gray-800 dark:text-white/90">{{ $socio['nome'] }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $socio['email'] }}</span>
                        </div>
                        <span class="text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">
                            {{ Dinheiro::brl($socio['cents']) }}
                        </span>
                    </div>
                @empty
                    <p class="tabela-vazia">Nenhum sócio configurado.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 cartao overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Vendas do mês</h2>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela min-w-[36rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="tabela-th text-left">Código</th>
                    <th scope="col" class="tabela-th text-left">Cliente</th>
                    <th scope="col" class="tabela-th text-left">Vendedor</th>
                    <th scope="col" class="tabela-th text-right">Valor</th>
                    <th scope="col" class="tabela-th text-right">Data</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($ultimas as $venda)
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
                                {{ $venda->vendida_em->format('d/m/Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabela-vazia">Nenhuma placa vendida neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
