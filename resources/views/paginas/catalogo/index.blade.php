@extends('layouts.app', ['title' => 'Catalogo'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Catalogo</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Cada linha e uma tabela de precos datada. A que esta em vigor nao aceita
            alteracao: reajuste nasce de uma copia.
        </p>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'versoes'])

    @php $celula = 'px-5 py-3 font-medium'; @endphp

    @include('paginas.catalogo.avisos')

    @if (! $vigente)
        <div class="border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400 mb-6 rounded-lg border p-4 text-sm">
            Nenhuma versao em vigor. Ate ativar uma, nao ha preco para calcular consulta nem fatura.
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        {{-- text-left explicito: o navegador centraliza th por
                             padrao, e o text-left da tabela nao vence isso. --}}
                        <th class="{{ $celula }} text-left">Tabela</th>
                        <th class="{{ $celula }} text-left">Situacao</th>
                        <th class="{{ $celula }} text-right">Precos</th>
                        <th class="{{ $celula }} text-right">Planos</th>
                        <th class="{{ $celula }} text-left">Vigencia</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($versoes as $v)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('catalogo.versoes.mostrar', $v) }}"
                                   class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">
                                    {{ $v->rotulo }}
                                </a>
                                @if ($v->observacao)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $v->observacao }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <x-catalogo.situacao :versao="$v" />
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $v->precos_count }}</td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $v->planos_count }}</td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                {{ $v->vigencia_inicio?->format('d/m/Y') ?? '—' }}
                                @if ($v->vigencia_fim)
                                    ate {{ $v->vigencia_fim->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($v->podeEditar())
                                    <form method="POST" action="{{ route('catalogo.versoes.ativar', $v) }}"
                                          onsubmit="return confirm('Ativar {{ $v->rotulo }}? Depois disso os precos ficam congelados.')">
                                        @csrf
                                        <button type="submit"
                                                class="bg-brand-500 hover:bg-brand-600 rounded-lg px-3 py-1.5 text-xs font-medium text-white">
                                            Ativar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhuma versao cadastrada. Rode <code>php artisan db:seed --class=CatalogoSeeder</code>
                                para importar a tabela de referencia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
