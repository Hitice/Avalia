{{-- Reajuste percentual do catalogo. Recebe $catalogo por @include. --}}

@php
    $campo = 'campo-linha';
@endphp

<div class="mb-6 cartao p-5">
    <form method="POST" action="{{ route('catalogo.reajustar', $catalogo) }}"
          class="flex flex-wrap items-end gap-3"
          onsubmit="return confirm('Aplicar o reajuste nos precos selecionados?')">
        @csrf

        <div>
            <label for="percentual" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Reajuste
            </label>
            <div class="flex items-center gap-2">
                <input id="percentual" name="percentual" type="number" step="0.01" min="-90" max="900"
                       placeholder="5" required class="{{ $campo }} w-24 text-right">
                <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
            </div>
            @error('percentual')
                <span class="erro-campo">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="categoria-reajuste" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Aplicar em
            </label>
            <select id="categoria-reajuste" name="categoria" class="{{ $campo }}">
                @foreach (\App\Models\Servico::CATEGORIAS as $chave => $rotulo)
                    <option value="{{ $chave }}">{{ $rotulo }}</option>
                @endforeach
                <option value="">Todos os servicos</option>
            </select>
        </div>

        <x-avalia.botao>Aplicar</x-avalia.botao>

        <p class="w-full text-xs text-gray-500 dark:text-gray-400">
            Arredonda para o centavo mais proximo. Vale so para o catalogo: consulta e fatura
            ja emitidas guardam o valor da epoca.
        </p>
    </form>
</div>
