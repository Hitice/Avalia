@php
    use App\Support\Dinheiro;
@endphp

@extends('layouts.ferramenta', ['title' => 'Códigos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">QR Code dinâmico</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Gere, imprima e venda. Só depois da venda você diz para onde cada código leva.
            </p>
        </div>

        <x-avalia.botao variante="secundario" :href="route('etiquetas.lotes.index')">Tiragens</x-avalia.botao>
    </div>

    @include('paginas.catalogo.avisos')

    {{-- Os dois passos lado a lado, na ordem em que acontecem. Um formulario
         so para gerar um ou cem, porque para quem usa e a mesma coisa com um
         numero diferente. --}}
    <div class="mb-5 grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('etiquetas.gerar') }}" class="cartao grid gap-4 p-6">
            @csrf

            <div>
                <h2 class="rotulo-grupo">1 · Gerar os códigos</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Nascem em branco, sem destino. É essa a ideia: imprimir antes de saber para
                    quem vai.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="w-28">
                    <label for="quantidade" class="rotulo-campo">Quantos</label>
                    <input id="quantidade" name="quantidade" type="number" min="1"
                           max="{{ config('etiquetas.lote_maximo') }}" required
                           value="{{ old('quantidade', 1) }}" class="campo">
                </div>

                <div class="min-w-[12rem] flex-1">
                    <label for="titulo" class="rotulo-campo">Apelido (opcional)</label>
                    <input id="titulo" name="titulo" type="text" maxlength="120" class="campo"
                           value="{{ old('titulo') }}" placeholder="Acrílico 4cm, gráfica do centro">
                </div>

                <x-avalia.botao>Gerar</x-avalia.botao>
            </div>

            @error('quantidade')<p class="erro-campo">{{ $message }}</p>@enderror

            <p class="ajuda-campo">
                Um código abre a ficha dele, com o QR para baixar. Mais de um abre uma tiragem, com
                o pacote numerado e o CSV que o CorelDRAW lê.
            </p>
        </form>

        <form method="POST" action="{{ route('etiquetas.apontar-codigo') }}" class="cartao grid gap-4 p-6">
            @csrf

            <div>
                <h2 class="rotulo-grupo">2 · Vendeu? Cadastre a URL</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Leia o código impresso na plaquinha e diga para onde ele deve levar.
                </p>
            </div>

            <div>
                <label for="codigo" class="rotulo-campo">Código da plaquinha</label>
                <input id="codigo" name="codigo" type="text" maxlength="20" required
                       value="{{ old('codigo') }}"
                       class="campo font-mono tracking-widest uppercase" placeholder="K7M2PX">
                @error('codigo')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="destino-rapido" class="rotulo-campo">Para onde leva</label>
                <input id="destino-rapido" name="destino" type="text" required class="campo"
                       value="{{ old('destino') }}" placeholder="https://wa.me/5531999999999">
                @error('destino')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>

            <div>
                <x-avalia.botao>Cadastrar destino</x-avalia.botao>
            </div>
        </form>
    </div>

    <form method="GET" class="cartao mb-5 flex flex-wrap items-end gap-3 p-5">
        <div class="min-w-[16rem] flex-1">
            <label for="busca" class="rotulo-campo">Buscar</label>
            <input id="busca" name="busca" type="search" value="{{ $filtros['busca'] }}" class="campo"
                   placeholder="Código, apelido, cliente ou destino">
        </div>

        <div>
            <label for="situacao" class="rotulo-campo">Situação</label>
            <select id="situacao" name="situacao" class="campo">
                <option value="">Todas</option>
                @foreach ($situacoes as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="lote" class="rotulo-campo">Tiragem</label>
            <select id="lote" name="lote" class="campo">
                <option value="">Todas</option>
                @foreach ($lotes as $lote)
                    <option value="{{ $lote->id }}" @selected((string) $filtros['lote'] === (string) $lote->id)>
                        {{ $lote->codigo }} · {{ $lote->titulo }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-avalia.botao variante="secundario">Filtrar</x-avalia.botao>
    </form>

    <div class="cartao overflow-hidden">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[56rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="tabela-th text-left">Código</th>
                        <th scope="col" class="tabela-th text-left">Aponta para</th>
                        <th scope="col" class="tabela-th text-left">Situação</th>
                        <th scope="col" class="tabela-th text-left">Vence</th>
                        <th scope="col" class="tabela-th text-right">Leituras</th>
                        <th scope="col" class="tabela-th text-right"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($etiquetas as $etiqueta)
                        @php $estado = $etiqueta->estado(); @endphp
                        <tr>
                            <td class="tabela-td">
                                <span class="font-mono font-medium tracking-wider text-gray-800 dark:text-white/90">{{ $etiqueta->codigo }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $etiqueta->titulo ?? $etiqueta->cliente_nome ?? ($etiqueta->lote?->codigo ?? 'Avulso') }}
                                </span>
                            </td>

                            <td class="tabela-td max-w-[22rem] truncate text-gray-600 dark:text-gray-300">
                                {{ $etiqueta->destino ?? '—' }}
                            </td>

                            <td class="tabela-td">
                                {{-- Carencia e vencida nao existem no banco: sao
                                     conta de data, e e o estado calculado que a
                                     leitura da plaquinha enxerga. --}}
                                <span @class([
                                    'etiqueta',
                                    'etiqueta-sucesso' => $estado === 'ativa',
                                    'etiqueta-alerta' => in_array($estado, ['carencia', 'em_branco'], true),
                                    'etiqueta-erro' => in_array($estado, ['vencida', 'suspensa'], true),
                                    'etiqueta-neutra' => $estado === 'baixada',
                                ])>
                                    @switch($estado)
                                        @case('carencia') Em carência @break
                                        @case('vencida') Vencida @break
                                        @default {{ $etiqueta->situacao->rotulo() }}
                                    @endswitch
                                </span>
                            </td>

                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                {{ $etiqueta->vence_em?->format('d/m/Y') ?? '—' }}
                            </td>

                            <td class="tabela-td text-right tabular-nums">{{ $etiqueta->total_acessos }}</td>

                            <td class="tabela-td text-right whitespace-nowrap">
                                <x-avalia.botao variante="secundario" tamanho="sm" :href="route('etiquetas.ficha', $etiqueta)">
                                    Abrir
                                </x-avalia.botao>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tabela-vazia">
                                Crie um código avulso acima, ou abra uma tiragem para gerar em lote.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$etiquetas" />
    </div>
@endsection
