@props([
    'acao' => '',
    'vendedores' => [],
    'competencias' => [],
    'escolha' => [],
])

@php
    use App\Models\Fatura;
    use App\Support\Rotulos;
@endphp

{{-- Filtros do financeiro, no mesmo molde do painel de consultas e da carteira.

     GET, com o recorte inteiro no endereço: a tela vira link que o operador
     manda para o colega conferir, e a exportação leva exatamente o que está na
     tela sem precisar de um segundo formulário.

     A busca aceita razão social ou CNPJ porque quem procura uma fatura tem um
     dos dois na mão. CPF de responsável fica de fora: não precisa ir para a
     barra de endereços para achar uma cobrança. --}}

<form method="GET" action="{{ $acao }}" class="cartao mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="busca" class="rotulo-campo">Cliente</label>
        <input id="busca" name="busca" type="text" class="campo"
               value="{{ $escolha['busca'] }}" placeholder="Razão social ou CNPJ">
    </div>

    <div>
        <label for="situacao" class="rotulo-campo">Situação</label>
        <select id="situacao" name="situacao" class="campo">
            <option value="">Todas</option>
            @foreach (Fatura::SITUACOES_PAGAMENTO as $valor)
                <option value="{{ $valor }}" @selected($escolha['situacao'] === $valor)>
                    {{ Rotulos::fatura($valor) }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="competencia" class="rotulo-campo">Competência</label>
        <select id="competencia" name="competencia" class="campo">
            <option value="">Todas</option>
            @foreach ($competencias as $competencia)
                <option value="{{ $competencia }}" @selected($escolha['competencia'] === $competencia)>
                    {{ Fatura::competenciaRotuloDe($competencia) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end gap-3">
        <div class="grow">
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
        <x-avalia.botao type="submit">Filtrar</x-avalia.botao>
    </div>
</form>
