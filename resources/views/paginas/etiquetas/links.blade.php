@extends('layouts.ferramenta', ['title' => 'Encurtador'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Encurtador</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Endereços longos atrás de um código curto, para caberem nos {{ $bytesDaTag }} bytes
            úteis da tag NFC.
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    <form method="POST" action="{{ route('etiquetas.links.salvar') }}" class="cartao mb-5 grid gap-4 p-6">
        @csrf

        <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-[18rem] flex-1">
                <label for="destino" class="rotulo-campo">Endereço longo</label>
                <input id="destino" name="destino" type="text" required class="campo"
                       value="{{ old('destino') }}"
                       placeholder="https://loja.com.br/promo?utm_source=nfc&utm_campaign=floripa2026">
                @error('destino')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>

            <div class="w-52">
                <label for="titulo" class="rotulo-campo">Apelido</label>
                <input id="titulo" name="titulo" type="text" maxlength="120" class="campo"
                       value="{{ old('titulo') }}" placeholder="Promo Floripa">
            </div>

            <div class="pt-[1.6rem]">
                <x-avalia.botao>Encurtar</x-avalia.botao>
            </div>
        </div>

        {{-- Endereco repetido devolve o codigo que ja existe: dois codigos para
             o mesmo lugar dividiriam a contagem de cliques ao meio, e ninguem
             saberia por que os numeros nao batem. --}}
        <p class="ajuda-campo">
            O mesmo endereço sempre devolve o mesmo código. Link que já foi aberto não se apaga,
            só se desliga: ele pode estar gravado numa tag que já saiu.
        </p>
    </form>

    <div class="cartao overflow-hidden">
        <form method="GET" class="border-b border-gray-100 p-5 dark:border-gray-800">
            <label for="busca" class="rotulo-campo">Buscar</label>
            <div class="flex flex-wrap items-center gap-3">
                <input id="busca" name="busca" type="search" value="{{ $busca }}"
                       class="campo min-w-[14rem] flex-1" placeholder="Código, apelido ou destino">
                <x-avalia.botao variante="secundario">Filtrar</x-avalia.botao>
            </div>
        </form>

        <div class="tabela-rolagem">
            <table class="tabela min-w-[52rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="tabela-th text-left">Link curto</th>
                        <th scope="col" class="tabela-th text-left">Aponta para</th>
                        <th scope="col" class="tabela-th text-right">Bytes</th>
                        <th scope="col" class="tabela-th text-right">Cliques</th>
                        <th scope="col" class="tabela-th text-left">Situação</th>
                        <th scope="col" class="tabela-th text-right">Ação</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($links as $link)
                        <tr>
                            <td class="tabela-td">
                                {{-- Copiar e o gesto que a tela existe para
                                     servir: o link curto so vale quando esta
                                     colado em algum lugar. --}}
                                <div x-data="copiavel" class="flex items-center gap-2">
                                    <span class="font-mono font-medium tracking-wider text-gray-800 dark:text-white/90">
                                        {{ $link->url() }}
                                    </span>

                                    <button type="button" x-on:click="copiar('{{ $link->url() }}')"
                                            x-bind:aria-label="copiado ? 'Copiado' : 'Copiar link'"
                                            class="shrink-0 text-gray-400 transition hover:text-brand-500">
                                        <svg x-show="! copiado" class="size-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                                            <rect x="9" y="9" width="11" height="11" rx="2" />
                                            <path stroke-linecap="round" d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" />
                                        </svg>
                                        <svg x-show="copiado" x-cloak class="size-4 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </button>
                                </div>

                                @if ($link->titulo)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $link->titulo }}</span>
                                @endif
                            </td>

                            <td class="tabela-td max-w-[22rem] truncate text-gray-600 dark:text-gray-300">
                                {{ $link->destino }}
                            </td>

                            {{-- O numero que decide se cabe na tag, ao lado do
                                 limite dela. E por isso que o encurtador
                                 existe. --}}
                            <td class="tabela-td text-right tabular-nums">
                                <span class="{{ $link->bytes() <= $bytesDaTag ? 'text-success-600' : 'text-error-600' }}">
                                    {{ $link->bytes() }}
                                </span>
                                <span class="text-gray-400">/ {{ $bytesDaTag }}</span>
                            </td>

                            <td class="tabela-td text-right tabular-nums">{{ $link->cliques }}</td>

                            <td class="tabela-td">
                                <span class="etiqueta {{ $link->ativo ? 'etiqueta-sucesso' : 'etiqueta-neutra' }}">
                                    {{ $link->ativo ? 'Ativo' : 'Desligado' }}
                                </span>
                            </td>

                            <td class="tabela-td text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('etiquetas.links.alternar', $link) }}">
                                        @csrf
                                        <x-avalia.botao variante="secundario" tamanho="sm">
                                            {{ $link->ativo ? 'Desligar' : 'Ligar' }}
                                        </x-avalia.botao>
                                    </form>

                                    {{-- Apagar so no que nunca foi aberto. Link
                                         ja clicado esta gravado em alguma tag,
                                         e apagar devolveria o codigo ao
                                         sorteio: quem encostasse o celular
                                         nela depois cairia no destino de
                                         outra pessoa. --}}
                                    @if ($link->cliques === 0)
                                        <form method="POST" action="{{ route('etiquetas.links.excluir', $link) }}"
                                              x-data x-on:submit="confirm('Apagar o link {{ $link->codigo }}? Ele nunca foi aberto.') || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <x-avalia.botao variante="secundario" tamanho="sm">Excluir</x-avalia.botao>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tabela-vazia">
                                Cole um endereço longo acima para receber o código curto.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$links" />
    </div>
@endsection
