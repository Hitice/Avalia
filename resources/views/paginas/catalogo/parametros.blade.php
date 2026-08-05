@extends('layouts.app', ['title' => 'Parâmetros'])

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

            <x-avalia.botao variante="secundario">Salvar</x-avalia.botao>
        </form>
    </div>
@endsection
