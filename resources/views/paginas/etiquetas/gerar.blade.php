@extends('layouts.app', ['title' => 'Gerar códigos'])

@section('content')
    <div class="mb-6">
        <h1 class="titulo-pagina">Gerar códigos</h1>
        <p class="rotulo-grupo mt-1">Gerador de QR</p>
    </div>

    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-avalia.cartao-indicador rotulo="Em branco, sem dono" :valor="$noBolo"
                                   ajuda="Prontos para entregar a um vendedor" />
        <x-avalia.cartao-indicador rotulo="Campanhas" :valor="$campanhas" />
    </div>

    {{-- Pagina propria, e so de administracao. Vivia na mesma tela do cadastro,
         e vendedor via o botao de gerar cem codigos ao lado do campo de apontar
         um. Gerar e tarefa de producao; apontar e tarefa de venda. --}}
    <form method="POST" action="{{ route('etiquetas.gerar') }}" class="cartao grid gap-4 p-6">
        @csrf

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

        <span class="ajuda-campo">
            A numeração continua da campanha anterior. Depois de gerar, baixe o ZIP na lista
            e entregue as placas pelo estoque.
        </span>

        @error('quantidade')<p class="erro-campo">{{ $message }}</p>@enderror
        @error('titulo')<p class="erro-campo">{{ $message }}</p>@enderror
    </form>
@endsection
