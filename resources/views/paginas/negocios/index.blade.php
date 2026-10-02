@extends('layouts.app', ['title' => 'Negócios'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Negócios</h1>
            <p class="rotulo-grupo mt-1">Os clientes da frente de marketing</p>
        </div>

    </div>

    @include('parciais.avisos')

    {{-- O servico: nome do estabelecimento entra, link curto de avaliacao sai.
         Fica no topo porque e o pedido mais frequente do cliente de marketing, e
         nao depende de ele estar na base. --}}
    <div class="cartao mb-6 p-5">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Link de avaliação do Google</h2>
        <p class="ajuda-campo mt-1">
            Digite o nome como ele aparece no perfil do Google. A gente acha o Place ID,
            monta o link de avaliação e devolve encurtado, pronto para o adesivo.
        </p>

        @if (session('linkPronto'))
            <div class="aviso aviso-ok mt-4 flex flex-wrap items-center gap-3">
                <span class="font-semibold">{{ session('linkPronto') }}</span>
                <button type="button" class="botao botao-secundario botao-sm"
                        onclick="navigator.clipboard.writeText('{{ session('linkPronto') }}')">Copiar</button>
            </div>
            <p class="ajuda-campo mt-2">Confira o link antes de cadastrar: abra e veja se cai no cliente certo. O gerador pode errar com nomes parecidos.</p>
        @endif

        @if (session('erro'))
            <p class="aviso aviso-erro mt-4">{{ session('erro') }}</p>
        @endif

        <form method="POST" action="{{ route('negocios.avaliacao.buscar') }}"
              class="mt-4 flex flex-wrap items-end gap-3">
            @csrf

            <div class="min-w-[18rem] flex-1">
                <label for="nome-lugar" class="rotulo-campo">Nome do estabelecimento</label>
                <input id="nome-lugar" name="nome" type="text" class="campo" required maxlength="150"
                       value="{{ old('nome') }}" placeholder="Como está no Google Meu Negócio">
                @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            @if (session('pedirCidade'))
                <div class="min-w-[12rem]">
                    <label for="cidade-lugar" class="rotulo-campo">Cidade</label>
                    <input id="cidade-lugar" name="cidade" type="text" class="campo" maxlength="120"
                           value="{{ old('cidade') }}" autofocus placeholder="Para desempatar">
                </div>
            @endif

            <x-avalia.botao>Gerar link</x-avalia.botao>
        </form>

        {{-- Mais de um homonimo: quem conhece o cliente escolhe, porque link errado
             manda a freguesia dele avaliar o concorrente. --}}
        @if (session('lugares'))
            <div class="mt-5">
                <p class="rotulo-grupo">O Google achou mais de um. Qual é o seu cliente?</p>

                <div class="mt-3 grid gap-2">
                    @foreach (session('lugares') as $lugar)
                        <form method="POST" action="{{ route('negocios.avaliacao.gerar') }}"
                              class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                            @csrf
                            <input type="hidden" name="place_id" value="{{ $lugar['place_id'] }}">
                            <input type="hidden" name="nome" value="{{ $lugar['nome'] }}">

                            <span>
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $lugar['nome'] }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $lugar['endereco'] }}</span>
                            </span>

                            <x-avalia.botao variante="secundario" class="botao-sm">É este</x-avalia.botao>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="cartao overflow-hidden">
        <form method="GET" class="barra-secao">
            <div class="min-w-[14rem] flex-1">
                <label for="busca" class="rotulo-campo">Buscar</label>
                <input id="busca" name="busca" type="search" class="campo" value="{{ $filtros['busca'] }}"
                       placeholder="Nome, responsável, cidade ou e-mail">
            </div>

            <div>
                <label for="situacao" class="rotulo-campo">Situação</label>
                <select id="situacao" name="situacao" class="campo" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach ($situacoes as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>
                            {{ $rotulo }} ({{ $porSituacao[$valor] ?? 0 }})
                        </option>
                    @endforeach
                </select>
            </div>

            <x-avalia.botao variante="secundario">Filtrar</x-avalia.botao>
        </form>

        <div class="tabela-rolagem">
            <table class="tabela min-w-[64rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="tabela-th text-left">Negócio</th>
                        <th class="tabela-th text-left">Responsável</th>
                        <th class="tabela-th text-left">Onde</th>
                        <th class="tabela-th text-left">Falta</th>
                        <th class="tabela-th text-left">Avaliação</th>
                        <th class="tabela-th text-right">Placas</th>
                        <th class="tabela-th text-left">Origem</th>
                        <th class="tabela-th text-left">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($negocios as $negocio)
                        <tr>
                            <td class="tabela-td">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $negocio->nome }}</span>
                                @if ($negocio->categoria)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $negocio->categoria }}</span>
                                @endif
                            </td>

                            <td class="tabela-td">
                                {{ $negocio->responsavel }}
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $negocio->whatsapp }} · {{ $negocio->email }}
                                </span>
                            </td>

                            <td class="tabela-td">
                                {{ $negocio->cidade ?: '—' }}
                                @unless ($negocio->atende_no_endereco)
                                    <span class="etiqueta etiqueta-neutra">vai ao cliente</span>
                                @endunless
                            </td>

                            <td class="tabela-td">
                                @php $falta = $negocio->faltaPara(); @endphp

                                @if ($falta === [])
                                    <span class="etiqueta">completo</span>
                                @else
                                    <span class="etiqueta etiqueta-alerta">{{ implode(', ', $falta) }}</span>
                                @endif
                            </td>

                            <td class="tabela-td">
                                @if ($negocio->linkAvaliacao)
                                    <a href="{{ route('l', ['codigo' => $negocio->linkAvaliacao->codigo]) }}"
                                       class="text-brand-600 dark:text-brand-400" target="_blank" rel="noopener">
                                        /l/{{ $negocio->linkAvaliacao->codigo }}
                                    </a>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $negocio->linkAvaliacao->cliques }} cliques
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('negocios.avaliacao.buscar') }}">
                                        @csrf
                                        <input type="hidden" name="nome" value="{{ $negocio->nome }}">
                                        <input type="hidden" name="cidade" value="{{ $negocio->cidade }}">
                                        <input type="hidden" name="negocio_id" value="{{ $negocio->id }}">
                                        <x-avalia.botao variante="secundario" class="botao-sm">Gerar</x-avalia.botao>
                                    </form>
                                @endif
                            </td>
                            <td class="tabela-td text-right tabular-nums">{{ $negocio->etiquetas_count }}</td>
                            <td class="tabela-td">{{ $negocio->origem ?: '—' }}</td>

                            <td class="tabela-td">
                                <form method="POST" action="{{ route('negocios.atualizar', $negocio) }}"
                                      class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')

                                    <select name="situacao" class="campo w-auto py-1.5"
                                            onchange="this.form.submit()">
                                        @foreach ($situacoes as $valor => $rotulo)
                                            <option value="{{ $valor }}"
                                                @selected($negocio->situacao->value === $valor)>{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                </form>

                                @if ($negocio->cadastrado_no_google_em)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        no ar desde {{ $negocio->cadastrado_no_google_em->format('d/m/Y') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="tabela-vazia">
                                Nenhum negócio ainda. Mande o link de cadastro acima para o cliente preencher.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $negocios->links() }}</div>
@endsection
