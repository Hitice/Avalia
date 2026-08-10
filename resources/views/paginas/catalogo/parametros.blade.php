@extends('layouts.app', ['title' => 'Parâmetros'])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <a href="{{ route('catalogo.tabela') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Tabelas
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
                <label for="margem_alvo" class="rotulo-campo">Margem alvo</label>
                <input id="margem_alvo" name="margem_alvo" type="number" step="0.01" min="0" max="99.99" required
                       value="{{ old('margem_alvo', number_format($catalogo->margem_alvo_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-28 text-right">
                @error('margem_alvo') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="degrau_margem" class="rotulo-campo">Degrau por faixa</label>
                <input id="degrau_margem" name="degrau_margem" type="number" step="0.01" min="0" max="20" required
                       value="{{ old('degrau_margem', number_format($catalogo->degrau_margem_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-28 text-right">
                @error('degrau_margem') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <x-avalia.botao variante="secundario">Salvar</x-avalia.botao>
        </form>

        <p class="ajuda-campo mt-5">
            A margem alvo é o que sobra depois do fornecedor, do imposto e da comissão.
            Ela vale inteira na faixa sem mínimo e cede um degrau a cada faixa seguinte,
            porque quem se compromete com mais consumo paga menos por consulta.
            Salvar aqui não altera preço nenhum: o reajuste é um botão na tabela.
        </p>

        {{-- A escada em números, na hora de decidir: dois campos abstratos
             viram sete alvos concretos, e o efeito do degrau nas faixas fundas
             aparece antes de alguém salvar. --}}
        @if ($alvos !== [])
            <div class="tabela-rolagem mt-5">
                <table class="tabela min-w-[36rem]">
                    <thead class="tabela-cabecalho"><tr>
                        @foreach ($alvos as $faixa => $bps)
                            <th scope="col" class="px-4 py-2 text-right font-medium">
                                {{ $faixa === 0 ? 'Sem mínimo' : Dinheiro::brl($faixa) }}
                            </th>
                        @endforeach
                    </tr></thead>
                    <tbody><tr>
                        @foreach ($alvos as $bps)
                            <td class="px-4 py-3 text-right tabular-nums text-gray-800 dark:text-white/90">
                                {{ number_format($bps / 100, 1, ',', '.') }}%
                            </td>
                        @endforeach
                    </tr></tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
