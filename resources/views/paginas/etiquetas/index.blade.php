@php
    use App\Support\Dinheiro;
@endphp

@extends('layouts.ferramenta', ['title' => 'Códigos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">QR Code dinâmico</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Gere, baixe e cadastre a URL no futuro.
            </p>
        </div>

    </div>

    @include('paginas.catalogo.avisos')

    {{-- Os dois passos lado a lado, na ordem em que acontecem. Um formulario
         so para gerar um ou cem, porque para quem usa e a mesma coisa com um
         numero diferente. --}}
    <div class="mb-5 grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('etiquetas.gerar') }}" class="cartao grid gap-4 p-6">
            @csrf

            <div>
                <h2 class="rotulo-grupo">1 · Gerar novo QR Code em branco</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Digite a quantidade de códigos a serem gerados. Para geração única, basta
                    gerar 1 unidade.
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
                    <label for="titulo" class="rotulo-campo">Campanha</label>
                    <input id="titulo" name="titulo" type="text" maxlength="120" class="campo"
                           value="{{ old('titulo') }}" placeholder="ex: Campanha Floripa 2026">
                </div>

                <x-avalia.botao>Gerar</x-avalia.botao>
            </div>

            @error('quantidade')<p class="erro-campo">{{ $message }}</p>@enderror
        </form>

        <form method="POST" action="{{ route('etiquetas.apontar-codigo') }}" class="cartao grid gap-4 p-6">
            @csrf

            <div>
                <h2 class="rotulo-grupo">2 · Área de cadastro</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Vendeu? Leia o código impresso e diga para onde ele deve levar.
                </p>
            </div>

            <div>
                <label for="codigo" class="rotulo-campo">Código impresso</label>
                <input id="codigo" name="codigo" type="text" maxlength="20" required
                       value="{{ old('codigo') }}"
                       class="campo font-mono tracking-widest uppercase" placeholder="K7M2PX">
                @error('codigo')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>

            {{-- O botao ao lado do campo, e nao embaixo: os dois formam uma
                 acao so, e quem acabou de digitar a URL ja tem o cursor ali. --}}
            <div>
                <label for="destino-rapido" class="rotulo-campo">Para onde leva</label>

                <div class="flex flex-wrap items-center gap-3">
                    <input id="destino-rapido" name="destino" type="text" required
                           class="campo min-w-[12rem] flex-1"
                           value="{{ old('destino') }}" placeholder="https://wa.me/5531999999999">

                    <x-avalia.botao class="shrink-0">Cadastrar destino</x-avalia.botao>
                </div>

                @error('destino')<p class="erro-campo">{{ $message }}</p>@enderror
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
            <label for="lote" class="rotulo-campo">Campanha</label>
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

    @if ($campanha && $pacote->isNotEmpty())
        {{-- O pacote da grafica mora aqui, e nao numa tela de tiragem: quem
             acabou de escolher a campanha esta a um clique de tudo que ela
             precisa, e nao ha uma segunda tela para lembrar que existe. --}}
        <div class="cartao mb-5 p-6"
             x-data="tiragem(@js(['pasta' => $campanha->pasta(), 'etiquetas' => $pacote]))">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <h2 class="rotulo-grupo">Pacote de {{ $campanha->titulo }}</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $pacote->count() }} códigos em SVG e PNG, numerados, mais o CSV que o
                        Print Merge do CorelDRAW lê para imprimir a tiragem inteira.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <x-avalia.botao x-on:click="baixar()" x-bind:disabled="gerando">
                            <span x-show="! gerando">Baixar o pacote</span>
                            <span x-show="gerando" x-cloak>Montando…</span>
                        </x-avalia.botao>

                        <span x-show="gerando" x-cloak class="text-sm text-gray-500 tabular-nums dark:text-gray-400">
                            <span x-text="feito"></span> de {{ $pacote->count() }}
                        </span>


                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="logo" class="size-4 rounded border-gray-300">
                            Com a marca
                        </label>
                    </div>

                    <p x-show="erro" x-cloak x-text="erro" class="aviso aviso-erro mt-4"></p>
                </div>

                {{-- A prova, no tamanho declarado. Leia da tela com o celular
                     antes de mandar cortar quinhentas placas. --}}
                <div class="shrink-0 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white">
                    <div x-ref="prova"></div>
                </div>
            </div>
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[56rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="tabela-th text-left"><span class="sr-only">QR</span></th>
                        <th scope="col" class="tabela-th text-left">Código</th>
                        <th scope="col" class="tabela-th text-left">Aponta para</th>
                        <th scope="col" class="tabela-th text-left">Situação</th>
                        <th scope="col" class="tabela-th text-left">Vence</th>
                        <th scope="col" class="tabela-th text-right">Leituras</th>
                        <th scope="col" class="tabela-th text-right"><span class="sr-only">Baixar</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800"
                       x-data="miniaturas(@js($miniaturas))">
                    @forelse ($etiquetas as $etiqueta)
                        @php $estado = $etiqueta->estado(); @endphp
                        <tr>
                            {{-- A miniatura desenhada na hora. O codigo manda
                                 no desenho, entao guardar imagem seria manter
                                 copia de algo que se refaz num milissegundo. --}}
                            <td class="tabela-td w-16">
                                {{-- Branco nos dois temas, de proposito: um QR
                                     sobre fundo escuro nao le, e a miniatura
                                     precisa parecer com o que sai impresso. --}}
                                <div id="qr-{{ $etiqueta->codigo }}" class="size-14 rounded bg-white p-0.5 dark:bg-white"></div>
                            </td>

                            <td class="tabela-td">
                                <a href="{{ route('etiquetas.ficha', $etiqueta) }}"
                                   class="font-mono font-medium tracking-wider text-gray-800 hover:text-brand-500 dark:text-white/90">
                                    {{ $etiqueta->codigo }}
                                </a>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $etiqueta->titulo ?? $etiqueta->lote?->titulo ?? 'Sem campanha' }}
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
                                <button type="button" x-on:click="baixar('{{ $etiqueta->codigo }}', 'svg')"
                                        class="botao botao-secundario botao-sm">SVG</button>
                                <button type="button" x-on:click="baixar('{{ $etiqueta->codigo }}', 'png')"
                                        class="botao botao-secundario botao-sm ml-1">PNG</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="tabela-vazia">
                                Gere os primeiros códigos acima. Eles nascem em branco, e ganham
                                destino depois da venda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$etiquetas" />
    </div>
@endsection
