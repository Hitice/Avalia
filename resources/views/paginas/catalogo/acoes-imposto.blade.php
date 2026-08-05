{{-- Parametros que governam margem, piso e preco alvo. Recebe $catalogo. --}}

@php
    use App\Support\Dinheiro;
    use App\Support\Margem;

    $comissao = $catalogo->comissaoBps();
    $margens = $catalogo->margemPorFaixa($faixas);
@endphp

<div class="cartao mb-6 p-5">
    <form method="POST" action="{{ route('catalogo.parametros', $catalogo) }}" class="flex flex-wrap items-end gap-3">
        @csrf
        @method('PUT')

        <div>
            <label for="imposto" class="rotulo-campo">Imposto</label>
            <div class="flex items-center gap-2">
                <input id="imposto" name="imposto" type="number" step="0.01" min="0" max="99.99" required
                       value="{{ old('imposto', number_format($catalogo->imposto_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-24 text-right">
                <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
            </div>
            @error('imposto') <span class="erro-campo">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="margem_alvo" class="rotulo-campo">Margem na maior faixa</label>
            <div class="flex items-center gap-2">
                <input id="margem_alvo" name="margem_alvo" type="number" step="0.01" min="0" max="99.99" required
                       value="{{ old('margem_alvo', number_format($catalogo->margem_alvo_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-24 text-right">
                <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
            </div>
            @error('margem_alvo') <span class="erro-campo">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="degrau_margem" class="rotulo-campo">Ganho por degrau</label>
            <div class="flex items-center gap-2">
                <input id="degrau_margem" name="degrau_margem" type="number" step="0.01" min="0" max="20" required
                       value="{{ old('degrau_margem', number_format($catalogo->degrau_margem_bps / 100, 2, '.', '')) }}"
                       class="campo-linha w-24 text-right">
                <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
            </div>
            @error('degrau_margem') <span class="erro-campo">{{ $message }}</span> @enderror
        </div>

        <x-avalia.botao variante="secundario">Recalcular</x-avalia.botao>

        <p class="w-full text-xs text-gray-500 dark:text-gray-400">
            A comissao do vendedor entra como custo, em {{ number_format($comissao / 100, 0) }}%: a margem e o que sobra
            depois de fornecedor, imposto e vendedor. A margem alvo vale para a MAIOR faixa, que e o piso comercial, e
            cada faixa abaixo dela ganha o degrau. Por isso o pacote maior sai mais barato por consulta sem nunca
            render menos que {{ $catalogo->margemAlvoRotulo() }}.
        </p>
    </form>
</div>

<div class="cartao mb-6 p-5">
    <form method="POST" action="{{ route('catalogo.precificar', $catalogo) }}"
          class="flex flex-wrap items-center gap-3"
          onsubmit="return confirm('Recalcular todos os precos pela escada de margem?')">
        @csrf

        <x-avalia.botao>Aplicar escada de margem</x-avalia.botao>

        <p class="flex-1 text-xs text-gray-500 dark:text-gray-400">
            Reescreve o preco de cada celula pela margem da faixa dela. Servico sem custo cadastrado fica de fora.
        </p>
    </form>

    @if ($margens !== [])
        <div class="mt-4 overflow-x-auto">
            <table class="tabela">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="tabela-th text-left">Faixa</th>
                        @foreach ($faixas as $faixa)
                            <th class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                {{ $faixa === 0 ? 'Sem mínimo' : Dinheiro::brl($faixa) }}
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
    @endif
</div>
