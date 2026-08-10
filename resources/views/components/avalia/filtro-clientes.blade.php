@props([
    'acao' => '',
    'vendedores' => [],
    'planos' => [],
    'escolha' => [],
])

@php
    use App\Support\Dinheiro;
    use App\Support\FiltroClientes;
@endphp

{{-- Filtros da lista de clientes, no mesmo molde do painel de consultas.

     GET e sem botao de limpar escondido: o recorte inteiro fica no endereço,
     então a tela vira link que o operador manda para o colega conferir, e a
     exportação leva exatamente o que está na tela.

     A busca aceita razão social ou CNPJ no mesmo campo, porque quem procura um
     cliente tem um dos dois na mão e não sabe qual a tela espera. CPF de
     responsável fica de fora: dado de pessoa não precisa ir para a barra de
     endereços. --}}

<form method="GET" action="{{ $acao }}" class="cartao mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
    {{-- A aba de removidas é estado da tela, e precisa sobreviver ao filtro. --}}
    @if ($escolha['removidas'])
        <input type="hidden" name="removidas" value="1">
    @endif

    <div>
        <label for="busca" class="rotulo-campo">Cliente</label>
        <input id="busca" name="busca" type="text" class="campo"
               value="{{ $escolha['busca'] }}" placeholder="Razão social ou CNPJ">
    </div>

    <div>
        <label for="situacao" class="rotulo-campo">Situação</label>
        <select id="situacao" name="situacao" class="campo">
            @foreach (FiltroClientes::SITUACOES as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($escolha['situacao'] === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="vendedor" class="rotulo-campo">Vendedor</label>
        <select id="vendedor" name="vendedor" class="campo">
            <option value="">Todos</option>
            @foreach ($vendedores as $vendedor)
                <option value="{{ $vendedor->id }}" @selected($escolha['vendedor'] === (string) $vendedor->id)>
                    {{ $vendedor->nome }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end gap-3">
        <div class="grow">
            <label for="plano" class="rotulo-campo">Plano</label>
            <select id="plano" name="plano" class="campo">
                <option value="">Todos</option>
                @foreach ($planos as $plano)
                    <option value="{{ $plano->id }}" @selected($escolha['plano'] === (string) $plano->id)>
                        {{ $plano->nome }} · {{ Dinheiro::faixa($plano->consumo_minimo_cents) }}
                    </option>
                @endforeach
            </select>
        </div>
        <x-avalia.botao type="submit">Filtrar</x-avalia.botao>
    </div>
</form>
