@php
    use App\Support\Dinheiro;
@endphp

@extends('layouts.app', ['title' => 'Plaquinhas'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Plaquinhas</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Cada código é um endereço permanente. Trocar para onde ele leva não reimprime nada.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-avalia.botao variante="secundario" :href="route('etiquetas.lotes.index')">Tiragens</x-avalia.botao>
            <x-avalia.botao variante="secundario" :href="route('etiquetas.lotes.criar')">Gerar em lote</x-avalia.botao>
        </div>
    </div>

    @include('paginas.catalogo.avisos')

    {{-- Um codigo so, sem tiragem: e o QR dinamico vendido sem placa. Fica no
         topo da lista, e nao numa tela propria, porque e um campo e um botao. --}}
    <form method="POST" action="{{ route('etiquetas.avulsa') }}" class="cartao mb-5 flex flex-wrap items-end gap-3 p-5">
        @csrf

        <div class="min-w-[16rem] flex-1">
            <label for="avulsa-titulo" class="rotulo-campo">Criar um código avulso</label>
            <input id="avulsa-titulo" name="titulo" type="text" maxlength="120" class="campo"
                   placeholder="Apelido interno: Padaria do Zé, balcão">
        </div>

        <div>
            <label for="avulsa-tipo" class="rotulo-campo">Tipo</label>
            <select id="avulsa-tipo" name="tipo" class="campo">
                @foreach ($tipos as $valor => $rotulo)
                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <x-avalia.botao>Criar código</x-avalia.botao>
    </form>

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
