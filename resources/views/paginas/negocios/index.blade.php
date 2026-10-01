@extends('layouts.app', ['title' => 'Negócios'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Negócios</h1>
            <p class="rotulo-grupo mt-1">Os clientes da frente de marketing</p>
        </div>

        {{-- O link que o vendedor manda. `origem` identifica quem distribuiu, e e
             o que evita cadastro orfao, o mesmo problema que a venda de plaquinha
             teve antes de ter `vendedor_id`. --}}
        <div class="min-w-[22rem]">
            <label for="link" class="rotulo-campo">Link de cadastro para enviar ao cliente</label>
            <input id="link" type="text" class="campo" readonly
                   value="{{ route('cadastro-negocio') }}?origem=SEU_NOME"
                   onfocus="this.select()">
            <span class="ajuda-campo">Troque SEU_NOME por quem está enviando.</span>
        </div>
    </div>

    @include('paginas.catalogo.avisos')

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
                            <td colspan="7" class="tabela-vazia">
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
