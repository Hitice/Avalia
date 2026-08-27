@props([
    'acao' => '',
    'vendedores' => [],
    'ufs' => [],
    'cidades' => [],
    'origens' => [],
    'escolha' => [],
    'comVendedor' => true,
])

@php
    use App\Support\FiltroLeads;
@endphp

{{-- Filtros da base de leads, no mesmo molde do filtro de clientes.

     Aqui o filtro nao serve so para procurar: é ele que monta o recorte que vai
     ser compartilhado com um vendedor. Por isso GET, e por isso o recorte
     inteiro vive na barra de endereços: a tela vira link, a exportação leva
     exatamente o que está nela, e a ação em lote sabe agir sobre o recorte
     inteiro e não só sobre a página aberta. --}}

<form method="GET" action="{{ $acao }}" class="cartao mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
    {{-- A aba de removidos é estado da tela, e precisa sobreviver ao filtro. --}}
    @if (! empty($escolha['removidos']))
        <input type="hidden" name="removidos" value="1">
    @endif

    <div>
        <label for="busca" class="rotulo-campo">Lead</label>
        <input id="busca" name="busca" type="text" class="campo"
               value="{{ $escolha['busca'] }}" placeholder="Nome, CNPJ, código, cidade ou contato">
    </div>

    <div>
        <label for="uf" class="rotulo-campo">UF</label>
        <select id="uf" name="uf" class="campo">
            <option value="">Todas</option>
            @foreach ($ufs as $uf)
                <option value="{{ $uf }}" @selected($escolha['uf'] === $uf)>{{ $uf }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="cidade" class="rotulo-campo">Cidade</label>
        <select id="cidade" name="cidade" class="campo">
            <option value="">Todas</option>
            @foreach ($cidades as $cidade)
                <option value="{{ $cidade }}" @selected($escolha['cidade'] === $cidade)>{{ $cidade }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="contato" class="rotulo-campo">Contato</label>
        <select id="contato" name="contato" class="campo">
            @foreach (FiltroLeads::CONTATOS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($escolha['contato'] === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="documento" class="rotulo-campo">CNPJ</label>
        <select id="documento" name="documento" class="campo">
            @foreach (FiltroLeads::DOCUMENTOS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($escolha['documento'] === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="situacao" class="rotulo-campo">Situação</label>
        <select id="situacao" name="situacao" class="campo">
            @foreach (FiltroLeads::situacoes() as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($escolha['situacao'] === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="origem" class="rotulo-campo">Origem</label>
        <select id="origem" name="origem" class="campo">
            <option value="">Todas</option>
            @foreach ($origens as $origem)
                <option value="{{ $origem }}" @selected($escolha['origem'] === $origem)>{{ $origem }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end gap-3">
        @if ($comVendedor)
            <div class="grow">
                <label for="vendedor" class="rotulo-campo">Vendedor</label>
                <select id="vendedor" name="vendedor" class="campo">
                    <option value="">Todos</option>
                    {{-- "Ainda sem vendedor" é a pergunta que a administração
                         faz antes de distribuir: o que está parado na base. --}}
                    <option value="sem" @selected($escolha['vendedor'] === 'sem')>Ainda sem vendedor</option>
                    @foreach ($vendedores as $vendedor)
                        <option value="{{ $vendedor->id }}" @selected($escolha['vendedor'] === (string) $vendedor->id)>
                            {{ $vendedor->nome }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <x-avalia.botao type="submit" class="{{ $comVendedor ? '' : 'ml-auto' }}">Filtrar</x-avalia.botao>
    </div>
</form>
