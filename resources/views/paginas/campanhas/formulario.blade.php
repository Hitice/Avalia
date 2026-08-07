@extends('layouts.app', ['title' => $campanha->exists ? $campanha->nome : 'Nova campanha'])

@php
    // Ids ja vinculados, para as caixas voltarem marcadas na edicao.
    $clientesEscolhidos = old('clientes', $campanha->exists ? $campanha->clientes->pluck('id')->all() : []);
    $servicosEscolhidos = old('servicos', $campanha->exists ? $campanha->servicos->pluck('id')->all() : []);
    $caixa = 'text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900';
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $campanha->exists ? $campanha->nome : 'Nova campanha' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Defina o público e comunique a oferta. Preços continuam definidos no catálogo.
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    <form method="POST"
          action="{{ $campanha->exists ? route('campanhas.atualizar', $campanha) : route('campanhas.salvar') }}"
          class="cartao grid gap-5 p-6">
        @csrf
        @if ($campanha->exists)
            @method('PUT')
        @endif

        <div>
            <label class="rotulo-campo" for="nome">Nome</label>
            <input class="campo" id="nome" name="nome" value="{{ old('nome', $campanha->nome) }}" required>
            <span class="ajuda-campo">Vira o selo do banner na página pública enquanto a campanha estiver vigente.</span>
            @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="rotulo-campo" for="oferta">Oferta</label>
            <textarea class="campo" id="oferta" name="oferta" rows="3" required>{{ old('oferta', $campanha->oferta) }}</textarea>
            <span class="ajuda-campo">
                Vira o texto do convite. Não cite preço, custo, margem nem nome de fornecedor:
                a vitrine recusa o texto e volta ao padrão.
            </span>
            @error('oferta') <span class="erro-campo">{{ $message }}</span> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="rotulo-campo" for="inicio">Início</label>
                <input class="campo" id="inicio" type="date" name="inicio"
                       value="{{ old('inicio', $campanha->inicio?->format('Y-m-d')) }}" required>
                @error('inicio') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="rotulo-campo" for="fim">Fim</label>
                <input class="campo" id="fim" type="date" name="fim"
                       value="{{ old('fim', $campanha->fim?->format('Y-m-d')) }}">
                <span class="ajuda-campo">Em branco, vale até ser encerrada.</span>
                @error('fim') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>
        </div>

        <fieldset>
            <legend class="rotulo-campo">Empresas elegíveis</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @forelse ($clientes as $cliente)
                    <label class="rotulo-opcao">
                        <input type="checkbox" name="clientes[]" value="{{ $cliente->id }}" class="{{ $caixa }}"
                               @checked(in_array($cliente->id, $clientesEscolhidos))>
                        {{ $cliente->razao_social }}
                    </label>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cadastre uma empresa antes de definir o público da campanha.</p>
                @endforelse
            </div>
        </fieldset>

        <fieldset>
            <legend class="rotulo-campo">Serviços envolvidos</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @forelse ($servicos as $servico)
                    <label class="rotulo-opcao">
                        <input type="checkbox" name="servicos[]" value="{{ $servico->id }}" class="{{ $caixa }}"
                               @checked(in_array($servico->id, $servicosEscolhidos))>
                        {{ $servico->nome }}
                    </label>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cadastre um serviço antes de vinculá-lo à campanha.</p>
                @endforelse
            </div>
        </fieldset>

        <div class="flex gap-3">
            <x-avalia.botao>{{ $campanha->exists ? 'Salvar' : 'Criar campanha' }}</x-avalia.botao>
            <x-avalia.botao variante="secundario" :href="route('campanhas.index')">Cancelar</x-avalia.botao>
        </div>
    </form>
@endsection
