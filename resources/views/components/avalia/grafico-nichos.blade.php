@props(['fatias'])

@php
    $total = (int) $fatias->sum('placas');

    // Uma matiz so, do escuro ao claro na ordem do tamanho: a fatia maior e a
    // mais escura, e a cor diz a posicao sem a legenda. "Outros" e cinza por
    // nao ser um nicho. Nomes literais, para o build enxergar as classes.
    $cores = [
        ['fatia' => 'stroke-brand-600', 'ponto' => 'bg-brand-600'],
        ['fatia' => 'stroke-brand-500', 'ponto' => 'bg-brand-500'],
        ['fatia' => 'stroke-brand-400', 'ponto' => 'bg-brand-400'],
        ['fatia' => 'stroke-brand-300', 'ponto' => 'bg-brand-300'],
        ['fatia' => 'stroke-brand-200', 'ponto' => 'bg-brand-200'],
    ];
    $cinza = ['fatia' => 'stroke-gray-300 dark:stroke-gray-600', 'ponto' => 'bg-gray-300 dark:bg-gray-600'];

    $raio = 40;
    $volta = 2 * M_PI * $raio;
    $percorrido = 0;
@endphp

@if ($total === 0)
    <p class="tabela-vazia mt-6">Sem vendas no mês.</p>
@else
    <div class="mt-4 flex items-center gap-6">
        <svg viewBox="0 0 100 100" class="size-36 shrink-0" role="img" aria-label="Vendas do mês por nicho">
            <g transform="rotate(-90 50 50)">
                @foreach ($fatias as $i => $f)
                    @php
                        $cor = $f['nome'] === 'Outros' ? $cinza : ($cores[$i] ?? $cinza);
                        $arco = $f['placas'] / $total * $volta;
                        // Dois pontos de folga entre fatias, menos quando ha uma so.
                        $folga = $fatias->count() > 1 ? min(2, $arco / 2) : 0;
                    @endphp
                    <circle cx="50" cy="50" r="{{ $raio }}" fill="none" stroke-width="14"
                            class="{{ $cor['fatia'] }}"
                            stroke-dasharray="{{ $arco - $folga }} {{ $volta - $arco + $folga }}"
                            stroke-dashoffset="{{ -$percorrido }}">
                        <title>{{ $f['nome'] }} · {{ $f['placas'] }} {{ $f['placas'] === 1 ? 'placa' : 'placas' }} ({{ round($f['placas'] / $total * 100) }}%)</title>
                    </circle>
                    @php $percorrido += $arco; @endphp
                @endforeach
            </g>
            <text x="50" y="50" text-anchor="middle" dominant-baseline="central" class="fill-gray-800 dark:fill-white/90" font-size="20" font-weight="600">{{ $total }}</text>
        </svg>

        <ul class="min-w-0 flex-1 space-y-1.5 text-sm">
            @foreach ($fatias as $i => $f)
                @php $cor = $f['nome'] === 'Outros' ? $cinza : ($cores[$i] ?? $cinza); @endphp
                <li class="flex items-center gap-2">
                    <span class="size-2.5 shrink-0 rounded-full {{ $cor['ponto'] }}"></span>
                    <span class="truncate text-gray-800 dark:text-white/90">{{ $f['nome'] }}</span>
                    <span class="ml-auto shrink-0 tabular-nums text-gray-500 dark:text-gray-400">{{ $f['placas'] }} · {{ round($f['placas'] / $total * 100) }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
