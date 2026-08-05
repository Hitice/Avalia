@extends('layouts.app', ['title' => 'Parâmetros'])

@php
    use App\Support\Dinheiro;

    $comissao = $catalogo->comissaoBps();
    $margens = $catalogo->margemPorFaixa($faixas);
@endphp

@section('content')
    <a href="{{ route('catalogo.tabela') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Catálogo
    </a>

    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">Parâmetros comerciais</h1>

    @include('paginas.catalogo.avisos')

    <div class="cartao p-6">
        <form method="POST" action="{{ route('catalogo.parametros.salvar', $catalogo) }}"
              class="flex flex-wrap items-end gap-4">
            @csrf
            @method('PUT')

            <div>
                <label for="imposto" class="rotulo-campo">Imposto</label>
                <input id="imposto" name="imposto" type="number" step="0.01" min="0" max="99.99" required
                       value="{{ old('imposto', number_format($catalogo->imposto_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-28 text-right">
                @error('imposto') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="margem_alvo" class="rotulo-campo">Margem na maior faixa</label>
                <input id="margem_alvo" name="margem_alvo" type="number" step="0.01" min="0" max="99.99" required
                       value="{{ old('margem_alvo', number_format($catalogo->margem_alvo_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-28 text-right">
                @error('margem_alvo') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="degrau_margem" class="rotulo-campo">Ganho por degrau</label>
                <input id="degrau_margem" name="degrau_margem" type="number" step="0.01" min="0" max="20" required
                       value="{{ old('degrau_margem', number_format($catalogo->degrau_margem_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-28 text-right">
                @error('degrau_margem') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <x-avalia.botao variante="secundario">Salvar</x-avalia.botao>
        </form>
    </div>

    @if ($margens !== [])
        <div class="cartao mt-6 p-6">
            <h2 class="mb-1 font-medium text-gray-800 dark:text-white/90">Escada de margem</h2>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                A comissão do vendedor entra como custo, em {{ number_format($comissao / 100, 0) }}%.
                O alvo vale para a maior faixa; cada faixa abaixo ganha o degrau, e e isso que faz o
                pacote maior sair mais barato por consulta sem render menos que o piso.
            </p>

            <div class="overflow-x-auto">
                <table class="tabela">
                    <thead class="tabela-cabecalho">
                        <tr>
                            <th class="tabela-th text-left">Faixa</th>
                            @foreach ($faixas as $faixa)
                                <th class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                    {{ Dinheiro::faixa($faixa) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="tabela-td text-left text-gray-500 dark:text-gray-400">Margem</td>
                            @foreach ($faixas as $faixa)
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ number_format($margens[$faixa] / 100, 0) }}%
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('catalogo.precificar', $catalogo) }}" class="mt-6"
                  onsubmit="return confirm('Recalcular todos os preços pela escada de margem?')">
                @csrf
                <x-avalia.botao>Aplicar escada de margem</x-avalia.botao>
            </form>
        </div>
    @endif
@endsection
