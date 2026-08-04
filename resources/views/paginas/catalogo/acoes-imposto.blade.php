{{-- Aliquota de imposto usada no calculo de margem. Recebe $catalogo. --}}

@php
    $campo = 'h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="POST" action="{{ route('catalogo.imposto', $catalogo) }}" class="flex flex-wrap items-end gap-3">
        @csrf
        @method('PUT')

        <div>
            <label for="imposto" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Imposto sobre a venda
            </label>
            <div class="flex items-center gap-2">
                <input id="imposto" name="imposto" type="number" step="0.01" min="0" max="99.99" required
                       value="{{ old('imposto', number_format($catalogo->imposto_bps / 100, 2, '.', '')) }}"
                       class="{{ $campo }} w-24 text-right">
                <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
            </div>
            @error('imposto')
                <span class="text-error-600 dark:text-error-400 mt-1.5 block text-xs">{{ $message }}</span>
            @enderror
        </div>

        <x-avalia.botao>Recalcular</x-avalia.botao>

        <p class="w-full text-xs text-gray-500 dark:text-gray-400">
            Estimativa registrada no PDD, ainda a confirmar com a contabilidade. Entra no calculo da
            margem e do piso de preco de todos os servicos.
        </p>
    </form>
</div>
