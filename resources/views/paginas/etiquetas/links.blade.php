@extends('layouts.app', ['title' => 'Encurtador'])

@section('content')
    <x-avalia.cabecalho-pagina titulo="Encurtador">
        <x-slot:subtitulo>
            Endereços longos atrás de um código curto, para caberem nos {{ $bytesDaTag }} bytes
                        úteis da tag NFC.
        </x-slot:subtitulo>
    </x-avalia.cabecalho-pagina>

    @include('parciais.avisos')

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

            <div class="w-full sm:w-52">
                <label for="titulo" class="rotulo-campo">Nome interno</label>
                <input id="titulo" name="titulo" type="text" maxlength="120" class="campo"
                       value="{{ old('titulo') }}" placeholder="Promo Floripa">
            </div>

            <div class="pt-[1.6rem]">
                <x-avalia.botao>Encurtar</x-avalia.botao>
            </div>
        </div>

        {{-- O endereco escolhido mora na RAIZ do dominio, junto das paginas do
             site. Por isso o campo mostra o prefixo: o que se digita ali vira
             um endereco publico da casa, e nao um parametro. --}}
        <div>
            <label for="apelido" class="rotulo-campo">Endereço personalizado (opcional)</label>

            <div class="flex items-center">
                <span class="rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    {{ rtrim(parse_url(config('app.url'), PHP_URL_HOST) ?: 'avaliaone.com.br', '/') }}/
                </span>
                <input id="apelido" name="apelido" type="text" maxlength="{{ App\Support\Apelido::TAMANHO_MAXIMO }}"
                       class="campo rounded-l-none" value="{{ old('apelido') }}" placeholder="MarthaNegocios">
            </div>

            @error('apelido')<p class="erro-campo">{{ $message }}</p>@enderror
        </div>

        {{-- Endereco repetido devolve o codigo que ja existe: dois codigos para
             o mesmo lugar dividiriam a contagem de cliques ao meio, e ninguem
             saberia por que os numeros nao batem. --}}
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

                                {{-- O codigo sorteado tambem abre, sempre. E o
                                     que esta gravado na tag de quem recebeu o
                                     link antes de ele ganhar nome. --}}
                                @if ($link->apelido)
                                    <span class="mt-0.5 block font-mono text-xs text-gray-400">
                                        também em {{ $link->urlDoCodigo() }}
                                    </span>
                                @endif

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
                                              x-data x-on:submit="confirm('Apagar o link {{ $link->codigo }}?') || $event.preventDefault()">
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
