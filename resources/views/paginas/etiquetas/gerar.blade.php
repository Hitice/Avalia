@extends('layouts.app', ['title' => 'Gerador QR Code'])

@section('content')
    <x-avalia.cabecalho-pagina titulo="Gerador QR Code" rotulo="Gerador de QR" />
    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-avalia.cartao-indicador rotulo="Livres" :valor="$noBolo"
                                   />
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

        <span class="ajuda-campo">Numeração contínua entre campanhas.</span>

        @error('quantidade')<p class="erro-campo">{{ $message }}</p>@enderror
        @error('titulo')<p class="erro-campo">{{ $message }}</p>@enderror
    </form>
@endsection
