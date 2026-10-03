@props(['porDia', 'mes', 'meta' => null])

@php
    use App\Support\Dinheiro;

    // A escala considera a previsao: barra vazia mais alta que a cheia nao
    // pode sair do quadro.
    $maior = max(1, $porDia->max('placas'), (int) ($meta['porDia'] ?? 0));

    // Geometria em PHP, e nao em JavaScript: o servidor ja tem os numeros, e
    // desenhar no cliente faria a tela aparecer vazia para preencher depois.
    $g = ['w' => 760, 'h' => 170, 'pb' => 26, 'pt' => 14];
    $g['util'] = $g['h'] - $g['pb'] - $g['pt'];
    $g['base'] = $g['pt'] + $g['util'];
    $passo = $g['w'] / max(1, $porDia->count());
    $barra = max(3, min(18, $passo * 0.62));
@endphp

@if ($meta)
    <p class="subtitulo-pagina">Meta {{ $meta['meta'] }} · feitas {{ $meta['vendidas'] }} · faltam {{ $meta['faltam'] }}@if ($meta['porDia'] > 0) · {{ $meta['porDia'] }} por dia nos {{ $meta['diasRestantes'] }} dias que restam @endif</p>
@endif

@if ($porDia->sum('placas') === 0 && ! ($meta && $meta['porDia'] > 0))
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

            {{-- Dia que ainda vem: a barra vazia e o que ele precisa render. --}}
            @if ($meta && $meta['porDia'] > 0 && $d['dia'] > $meta['hoje'])
                @php $alturaMeta = $meta['porDia'] / $maior * $g['util']; @endphp
                <rect class="serie-meta" x="{{ $x }}" y="{{ $g['base'] - $alturaMeta }}" width="{{ $barra }}" height="{{ $alturaMeta }}" rx="2">
                    <title>{{ $d['rotulo'] }}: {{ $meta['porDia'] }} para a meta</title>
                </rect>
            @endif

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
