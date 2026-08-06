@props([
    'acao' => '',
    'servicos' => [],
    'escolha' => [],
])

@php
    use App\Support\FiltroConsultas;
@endphp

{{-- Filtros do painel de consultas.

     GET e sem botao de limpar escondido: o recorte inteiro fica no endereco,
     entao a tela vira link que o operador manda para o colega conferir.

     Nao ha campo de CPF ou CNPJ de proposito. Filtro vira query string, e query
     string vai para o log do servidor e para o historico do navegador. O
     protocolo responde a mesma pergunta sem levar dado pessoal junto. --}}

<form method="GET" action="{{ $acao }}" class="cartao mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="periodo" class="rotulo-campo">Período</label>
        <select id="periodo" name="periodo" class="campo">
            @foreach (FiltroConsultas::PERIODOS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($escolha['periodo'] === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="servico" class="rotulo-campo">Serviço</label>
        <select id="servico" name="servico" class="campo">
            <option value="">Todos</option>
            @foreach ($servicos as $servico)
                <option value="{{ $servico->id }}" @selected($escolha['servico'] === (string) $servico->id)>
                    {{ $servico->nome }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="situacao" class="rotulo-campo">Resultado</label>
        <select id="situacao" name="situacao" class="campo">
            @foreach (FiltroConsultas::SITUACOES as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($escolha['situacao'] === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end gap-3">
        <div class="grow">
            <label for="protocolo" class="rotulo-campo">Protocolo</label>
            <input id="protocolo" name="protocolo" type="text" class="campo"
                   value="{{ $escolha['protocolo'] }}" placeholder="Opcional">
        </div>
        <x-avalia.botao type="submit">Filtrar</x-avalia.botao>
    </div>
</form>
