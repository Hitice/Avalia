@extends('layouts.app', ['title' => $lead->exists ? $lead->nome : 'Novo lead'])

@php
    use App\Support\Documento;
@endphp

@section('content')
    <a href="{{ route('leads.index') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Leads
    </a>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $lead->exists ? $lead->nome : 'Novo lead' }}
        </h1>

        @if ($lead->exists)
            {{-- Fora do formulario principal: form dentro de form nao existe em
                 HTML. Remover tira do trabalho, e da para restaurar. --}}
            <form method="POST" action="{{ route('leads.remover', $lead) }}"
                  onsubmit="return confirm('Remover {{ $lead->nome }} da base? Dá para restaurar depois.')">
                @csrf
                @method('DELETE')
                <x-avalia.botao variante="secundario" tamanho="sm">Remover da base</x-avalia.botao>
            </form>
        @endif
    </div>

    @include('paginas.catalogo.avisos')

    @if ($errors->any())
        <div class="aviso aviso-erro mb-6">
            <p class="font-medium">Confira os campos destacados:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="cartao p-6">
        <form method="POST" action="{{ $lead->exists ? route('leads.atualizar', $lead) : route('leads.salvar') }}">
            @csrf
            @if ($lead->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nome" class="rotulo-campo">Nome</label>
                    <input id="nome" name="nome" type="text" class="campo" required
                           value="{{ old('nome', $lead->nome) }}">
                    @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cnpj" class="rotulo-campo">CNPJ</label>
                    <input id="cnpj" name="cnpj" type="text" class="campo"
                           value="{{ old('cnpj', Documento::formatarCnpj($lead->cnpj)) }}"
                           placeholder="00.000.000/0000-00">
                    {{-- Opcional e sem validacao de digito: metade da base chega
                         sem documento, e exigir CNPJ correto de quem ainda nao e
                         cliente jogaria fora o lead que a Receita vai confirmar. --}}
                    <span class="ajuda-campo">Opcional. Serve para reconhecer o lead que já virou cliente.</span>
                    @error('cnpj') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="codigo" class="rotulo-campo">Código da base</label>
                    <input id="codigo" name="codigo" type="text" class="campo"
                           value="{{ old('codigo', $lead->codigo) }}">
                    <span class="ajuda-campo">O número que o lead tinha na base de origem.</span>
                    @error('codigo') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cidade" class="rotulo-campo">Cidade</label>
                    <input id="cidade" name="cidade" type="text" class="campo"
                           value="{{ old('cidade', $lead->cidade) }}">
                    @error('cidade') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="uf" class="rotulo-campo">UF</label>
                    <input id="uf" name="uf" type="text" class="campo" maxlength="2"
                           value="{{ old('uf', $lead->uf) }}">
                    @error('uf') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="telefone" class="rotulo-campo">Telefone</label>
                    <input id="telefone" name="telefone" type="text" class="campo"
                           value="{{ old('telefone', $lead->telefone) }}">
                    @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="rotulo-campo">E-mail</label>
                    <input id="email" name="email" type="text" class="campo"
                           value="{{ old('email', $lead->email) }}">
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="origem" class="rotulo-campo">Origem</label>
                    <input id="origem" name="origem" type="text" class="campo"
                           value="{{ old('origem', $lead->origem) }}"
                           placeholder="Indicação, feira, base 12.pdf">
                    @error('origem') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-end">
                    <label class="rotulo-opcao">
                        <input type="checkbox" name="ativo" value="1" class="caixa"
                               @checked(old('ativo', $lead->ativo))>
                        Lead ativo
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <label for="observacao" class="rotulo-campo">Observação</label>
                    <textarea id="observacao" name="observacao" rows="3" class="campo">{{ old('observacao', $lead->observacao) }}</textarea>
                    <span class="ajuda-campo">O que o vendedor precisa saber antes de ligar. Ele lê isto na lista dele.</span>
                    @error('observacao') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>
            </div>

            @if ($lead->exists && $lead->vendedores->isNotEmpty())
                <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <span class="rotulo-grupo block">Compartilhado com</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($lead->vendedores as $vendedor)
                            <span class="etiqueta etiqueta-neutra">
                                {{ $vendedor->nome }} · desde
                                {{ \Illuminate\Support\Carbon::parse($vendedor->pivot->compartilhado_em)->format('d/m/Y') }}
                            </span>
                        @endforeach
                    </div>
                    <span class="ajuda-campo">A distribuição se faz na lista, selecionando os leads e escolhendo o vendedor.</span>
                </div>
            @endif

            <div class="mt-6 flex items-center gap-3">
                <x-avalia.botao type="submit">{{ $lead->exists ? 'Salvar' : 'Cadastrar' }}</x-avalia.botao>
                <x-avalia.botao variante="secundario" :href="route('leads.index')">Cancelar</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
