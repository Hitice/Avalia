@extends('layouts.app', ['title' => 'Nova tiragem'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Nova tiragem</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Os códigos nascem em branco: eles só ganham destino depois de a plaquinha ser vendida.
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    <form method="POST" action="{{ route('etiquetas.lotes.salvar') }}" class="cartao max-w-2xl p-6">
        @csrf

        <div class="campo-linha">
            <label for="titulo" class="rotulo-campo">Nome da tiragem</label>
            <input id="titulo" name="titulo" type="text" maxlength="120" required
                   value="{{ old('titulo') }}" class="campo" placeholder="Plaquinhas de balcão, setembro">
            <p class="ajuda-campo">Só para você achar a tiragem depois. Não aparece em lugar nenhum da placa.</p>
            @error('titulo')<p class="erro-campo">{{ $message }}</p>@enderror
        </div>

        <div class="campo-linha">
            <label for="quantidade" class="rotulo-campo">Quantas plaquinhas</label>
            <input id="quantidade" name="quantidade" type="number" min="1" max="{{ config('etiquetas.lote_maximo') }}"
                   required value="{{ old('quantidade', 100) }}" class="campo">
            <p class="ajuda-campo">
                Até {{ config('etiquetas.lote_maximo') }} por tiragem. Quem desenha os arquivos é o seu
                navegador, e acima disso ele fica preso por minutos.
            </p>
            @error('quantidade')<p class="erro-campo">{{ $message }}</p>@enderror
        </div>

        <div class="campo-linha">
            <label for="tipo" class="rotulo-campo">O que vai na plaquinha</label>
            <select id="tipo" name="tipo" class="campo">
                @foreach ($tipos as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <p class="ajuda-campo">
                A tag NFC é gravada com o mesmo endereço do QR, então ela nunca precisa ser regravada
                depois da venda.
            </p>
            @error('tipo')<p class="erro-campo">{{ $message }}</p>@enderror
        </div>

        <div class="campo-linha">
            <label for="observacao" class="rotulo-campo">Observação</label>
            <input id="observacao" name="observacao" type="text" maxlength="255"
                   value="{{ old('observacao') }}" class="campo" placeholder="Acrílico 4cm, gráfica do centro">
            @error('observacao')<p class="erro-campo">{{ $message }}</p>@enderror
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-avalia.botao>Abrir tiragem</x-avalia.botao>
            <a href="{{ route('etiquetas.lotes.index') }}" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
@endsection
