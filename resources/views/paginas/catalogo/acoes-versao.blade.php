{{-- O que da para fazer com esta versao, conforme a situacao dela.
     Rascunho: reajustar e ativar. Congelada: duplicar para reajustar.
     Recebe $versao por @include. --}}

@php
    $campo = 'rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $botao = 'bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white whitespace-nowrap';
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
    @if ($versao->podeEditar())
        <div class="flex flex-wrap items-start gap-6">
            <form method="POST" action="{{ route('catalogo.versoes.reajustar', $versao) }}"
                  class="flex flex-wrap items-end gap-3">
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
                        <span class="text-error-600 dark:text-error-400 mt-1.5 block text-xs">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="categoria-reajuste" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Aplicar em
                    </label>
                    <select id="categoria-reajuste" name="categoria" class="{{ $campo }}">
                        <option value="">Todos os servicos</option>
                        @foreach (\App\Models\Servico::CATEGORIAS as $chave => $rotulo)
                            <option value="{{ $chave }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="{{ $botao }}">Aplicar</button>
            </form>

            <form method="POST" action="{{ route('catalogo.versoes.ativar', $versao) }}"
                  class="ml-auto self-end"
                  onsubmit="return confirm('Ativar {{ $versao->rotulo }}? Depois disso os precos ficam congelados.')">
                @csrf
                <button type="submit" class="{{ $botao }}">Colocar em vigor</button>
            </form>
        </div>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Rascunho aceita edicao direta na tabela abaixo. Depois de em vigor, so por copia.
        </p>
    @else
        <form method="POST" action="{{ route('catalogo.versoes.duplicar', $versao) }}"
              class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-64 flex-1">
                <label for="rotulo" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Nome da nova tabela
                </label>
                <input id="rotulo" name="rotulo" type="text" required class="{{ $campo }} w-full"
                       value="{{ old('rotulo', 'Catálogo '.now()->addMonth()->format('m/Y')) }}">
                @error('rotulo')
                    <span class="text-error-600 dark:text-error-400 mt-1.5 block text-xs">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="{{ $botao }}">Duplicar para reajustar</button>
        </form>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Esta versao esta congelada porque contrato, consulta e fatura ja emitidos apontam
            para ela. A copia nasce em rascunho, com os mesmos precos, e pode ser ajustada a vontade.
        </p>
    @endif
</div>
