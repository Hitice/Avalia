@extends('layouts.app', ['title' => 'Contatos'])

@section('content')
    <x-avalia.cabecalho-pagina titulo="Contatos" subtitulo="Pessoas e empresas de todas as frentes" />

    @include('parciais.avisos')

    <div class="cartao overflow-hidden">
        <form method="GET" class="barra-secao">
            <div class="min-w-[14rem] flex-1">
                <label for="busca" class="rotulo-campo">Buscar</label>
                <input id="busca" name="busca" type="search" value="{{ $filtros['busca'] }}" class="campo" placeholder="Nome, documento, WhatsApp ou e-mail">
            </div>
            <div>
                <label for="papel" class="rotulo-campo">Papel</label>
                <select id="papel" name="papel" class="campo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($papeis as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['papel'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <x-avalia.botao variante="secundario">Filtrar</x-avalia.botao>
        </form>

        <div class="tabela-rolagem">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="tabela-th text-left">Contato</th>
                    <th scope="col" class="tabela-th text-left">Documento</th>
                    <th scope="col" class="tabela-th text-left">WhatsApp</th>
                    <th scope="col" class="tabela-th text-left">Papéis</th>
                    <th scope="col" class="tabela-th text-right">Interações</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($contatos as $contato)
                        <tr>
                            <td class="tabela-td">
                                <a href="{{ route('gestao.contatos.ver', $contato) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $contato->nome }}</a>
                                <span class="ajuda-campo">{{ $contato->email }}{{ $contato->cidade ? ' · '.$contato->cidade : '' }}</span>
                            </td>
                            <td class="tabela-td font-mono text-gray-600 dark:text-gray-300">{{ $contato->documento ? App\Support\Documento::formatar($contato->documento) : '' }}</td>
                            <td class="tabela-td font-mono text-gray-600 dark:text-gray-300">{{ $contato->whatsapp ?: $contato->telefone }}</td>
                            <td class="tabela-td">
                                @foreach ($contato->papeis() as $papel)
                                    <span class="etiqueta etiqueta-neutra mr-1">{{ $papel }}</span>
                                @endforeach
                            </td>
                            <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $contato->interacoes_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabela-vazia">Nenhum contato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$contatos" />
    </div>
@endsection
