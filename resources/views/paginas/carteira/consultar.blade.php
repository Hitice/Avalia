@extends('layouts.app', ['title' => 'Consultar'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Minha carteira</h1>
    </div>

    @include('paginas.carteira.abas')

    @if (session('erro'))
        <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
    @endif

    <div class="cartao p-6">
        <p class="ajuda-campo mb-5">
            Demonstração para fechar venda: consulte o documento do seu prospect e mostre o
            resultado na hora. Ninguém é cobrado; o custo da consulta sai da sua comissão.
            Você ainda tem {{ $restantes }} {{ $restantes === 1 ? 'demonstração' : 'demonstrações' }} hoje.
        </p>

        <form method="POST" action="{{ route('carteira.consultar.executar') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf

            <div>
                <label for="servico_id" class="rotulo-campo">Serviço</label>
                <select id="servico_id" name="servico_id" class="campo" required>
                    @foreach ($servicos as $servico)
                        <option value="{{ $servico->id }}" @selected(old('servico_id') == $servico->id)>
                            {{ $servico->numero }} · {{ $servico->nome }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="documento" class="rotulo-campo">CPF ou CNPJ</label>
                <input id="documento" name="documento" type="text" class="campo" required
                       inputmode="numeric" value="{{ old('documento') }}">
                @error('documento') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div class="sm:col-span-2">
                <x-avalia.botao>Consultar para demonstrar</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
