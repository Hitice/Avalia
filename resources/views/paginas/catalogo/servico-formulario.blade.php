@extends('layouts.app', ['title' => $servico->exists ? $servico->nome : 'Novo servico'])

@php
    use App\Support\Dinheiro;

    $rotulo = 'rotulo-campo';
    $campo = 'campo';
    $erro = 'erro-campo';
@endphp

@section('content')
    <a href="{{ route('catalogo.servicos.index') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Servicos
    </a>

    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">
        {{ $servico->exists ? $servico->nome : 'Novo servico' }}
    </h1>

    @include('paginas.catalogo.avisos')

    <div class="cartao p-6">
        <form method="POST"
              action="{{ $servico->exists ? route('catalogo.servicos.atualizar', $servico) : route('catalogo.servicos.salvar') }}">
            @csrf
            @if ($servico->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nome" class="{{ $rotulo }}">Nome comercial</label>
                    <input id="nome" name="nome" type="text" class="{{ $campo }}"
                           value="{{ old('nome', $servico->nome) }}" required>
                    <span class="ajuda-campo">
                        E o que o cliente ve. Nao use marca nem nomenclatura do fornecedor.
                    </span>
                    @error('nome') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="codigo" class="{{ $rotulo }}">Codigo</label>
                    @if ($servico->exists)
                        <input id="codigo" type="text" class="{{ $campo }} cursor-not-allowed opacity-60"
                               value="{{ $servico->codigo }}" disabled>
                        <span class="ajuda-campo">
                            Nao muda depois de criado: e por ele que franquia, relatorio e integracao
                            acham o servico.
                        </span>
                    @else
                        <input id="codigo" name="codigo" type="text" class="{{ $campo }}"
                               value="{{ old('codigo') }}" placeholder="score-positivo" required>
                        <span class="ajuda-campo">
                            Minusculas, numeros e hifen. Escolha com calma: e definitivo.
                        </span>
                    @endif
                    @error('codigo') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="categoria" class="{{ $rotulo }}">Categoria</label>
                    <select id="categoria" name="categoria" class="{{ $campo }}" required>
                        @foreach (\App\Models\Servico::CATEGORIAS as $chave => $nome)
                            <option value="{{ $chave }}" @selected(old('categoria', $servico->categoria) === $chave)>
                                {{ $nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('categoria') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                @unless ($servico->exists)
                    <div class="sm:col-span-2">
                        <label for="preco_base" class="{{ $rotulo }}">Preco inicial</label>
                        <input id="preco_base" name="preco_base" type="text" inputmode="decimal"
                               class="{{ $campo }}" value="{{ old('preco_base') }}" placeholder="6,31" required>
                        <span class="ajuda-campo">
                            Entra igual em {{ count($faixas) }} faixa(s) do catalogo. Servico sem preco nao
                            apareceria na matriz, que e o unico lugar onde se edita preco. Ajuste faixa a
                            faixa depois de criar.
                        </span>
                        @error('preco_base_cents') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                    </div>
                @endunless

                <div class="flex items-center">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="ativo" value="0">
                        <input type="checkbox" name="ativo" value="1" class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('ativo', $servico->ativo ?? true))>
                        Servico ativo
                    </label>
                </div>

                <div class="flex items-center">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="exige_liberacao" value="0">
                        <input type="checkbox" name="exige_liberacao" value="1" class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('exige_liberacao', $servico->exige_liberacao ?? false))>
                        Aguarda liberacao juridica
                    </label>
                </div>

                <p class="text-xs text-gray-500 sm:col-span-2 dark:text-gray-400">
                    Servico que aguarda liberacao aparece no catalogo e pode ser precificado, mas nao
                    entra em plano nenhum e nenhuma consulta sai para o fornecedor. E o estado dos
                    servicos de SCR ate a homologacao juridica e contratual.
                </p>
            </div>

            <div class="mt-6 flex gap-3">
                <x-avalia.botao>
                    {{ $servico->exists ? 'Salvar' : 'Criar servico' }}
                </x-avalia.botao>
                <x-avalia.botao variante="secundario" :href="route('catalogo.servicos.index')">Cancelar</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
