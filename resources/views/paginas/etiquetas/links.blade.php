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
            O mesmo endereço sempre devolve o mesmo código. Desligar não apaga: o código pode
            estar gravado numa tag que já saiu.
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
                                <span class="font-mono font-medium tracking-wider text-gray-800 dark:text-white/90">
                                    {{ $link->url() }}
                                </span>
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
                                <form method="POST" action="{{ route('etiquetas.links.alternar', $link) }}">
                                    @csrf
                                    <x-avalia.botao variante="secundario" tamanho="sm">
                                        {{ $link->ativo ? 'Desligar' : 'Ligar' }}
                                    </x-avalia.botao>
                                </form>
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
