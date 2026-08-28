@props([
    'acao' => '',
    'vendedores' => [],
    'ufs' => [],
    'cidades' => [],
    'escolha' => [],
    'comVendedor' => true,
])

{{-- Filtros da base de leads.

     Uma busca livre, e não um seletor por campo: quem procura um lead tem um
     dado na mão e não sabe qual campo a tela espera. Cidade e UF continuam
     próprias porque não se procura por elas, se recorta por elas, e a lista
     fechada evita errar a grafia.

     GET, e o recorte inteiro na barra de endereços: a tela vira link, a
     exportação leva exatamente o que está nela, e a ação em lote alcança o
     filtro inteiro e não só o que a tabela desenhou. --}}

<form method="GET" action="{{ $acao }}"
      class="cartao mb-6 grid gap-4 p-5 sm:grid-cols-2 {{ $comVendedor ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
    {{-- A aba de removidos é estado da tela, e precisa sobreviver ao filtro. --}}
    @if (! empty($escolha['removidos']))
        <input type="hidden" name="removidos" value="1">
    @endif

    {{-- Os recortes que não se digitam (sem contato, sem CNPJ, agendamento
         vencido) entram pelos cartões de número da tela, que são links. Aqui
         eles viajam escondidos para sobreviver a uma busca nova. --}}
    @foreach (['situacao', 'contato', 'documento'] as $escondido)
        @if (! empty($escolha[$escondido]))
            <input type="hidden" name="{{ $escondido }}" value="{{ $escolha[$escondido] }}">
        @endif
    @endforeach

    <div>
        <label for="busca" class="rotulo-campo">Buscar</label>
        <input id="busca" name="busca" type="text" class="campo" value="{{ $escolha['busca'] }}"
               placeholder="Nome, CNPJ, telefone, e-mail, responsável, situação">
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

    <div class="flex items-end gap-3">
        <div class="grow">
            <label for="cidade" class="rotulo-campo">Cidade</label>
            <select id="cidade" name="cidade" class="campo">
                <option value="">Todas</option>
                @foreach ($cidades as $cidade)
                    <option value="{{ $cidade }}" @selected($escolha['cidade'] === $cidade)>{{ $cidade }}</option>
                @endforeach
            </select>
        </div>

        @unless ($comVendedor)
            <x-avalia.botao type="submit">Filtrar</x-avalia.botao>
        @endunless
    </div>

    @if ($comVendedor)
        <div class="flex items-end gap-3">
            <div class="grow">
                <label for="vendedor" class="rotulo-campo">Vendedor</label>
                <select id="vendedor" name="vendedor" class="campo">
                    <option value="">Todos</option>
                    {{-- "Ainda sem vendedor" é a pergunta que a administração faz
                         antes de distribuir: o que está parado na base. --}}
                    <option value="sem" @selected($escolha['vendedor'] === 'sem')>Ainda sem vendedor</option>
                    @foreach ($vendedores as $vendedor)
                        <option value="{{ $vendedor->id }}" @selected($escolha['vendedor'] === (string) $vendedor->id)>
                            {{ $vendedor->nome }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-avalia.botao type="submit">Filtrar</x-avalia.botao>
        </div>
    @endif
</form>
