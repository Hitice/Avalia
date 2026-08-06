@extends('layouts.app', ['title' => $membro->exists ? $membro->nome : 'Nova pessoa'])

@section('content')
    <a href="{{ route('equipe.index') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Equipe
    </a>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $membro->exists ? $membro->nome : 'Nova pessoa' }}
        </h1>

        @if ($membro->exists)
            {{-- Fora do formulario principal: form dentro de form nao existe
                 em HTML. Nao mexe na senha atual, so manda o link novo. --}}
            <form method="POST" action="{{ route('equipe.convite', $membro) }}">
                @csrf
                <x-avalia.botao variante="secundario" tamanho="sm">
                    Enviar redefinição de senha
                </x-avalia.botao>
            </form>
        @endif
    </div>

    @include('paginas.catalogo.avisos')

    <div class="cartao p-6">
        <form method="POST"
              action="{{ $membro->exists ? route('equipe.atualizar', $membro) : route('equipe.salvar') }}"
              x-data="{ papel: '{{ old('papel', $membro->papel ?? 'vendedor') }}' }">
            @csrf
            @if ($membro->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nome" class="rotulo-campo">Nome</label>
                    <input id="nome" name="nome" type="text" class="campo" required
                           value="{{ old('nome', $membro->nome) }}">
                    @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="rotulo-campo">E-mail de acesso</label>
                    <input id="email" name="email" type="email" class="campo" required
                           value="{{ old('email', $membro->email) }}">
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="papel" class="rotulo-campo">Função</label>
                    <select id="papel" name="papel" class="campo" required x-model="papel">
                        <option value="vendedor">Vendedor</option>
                        <option value="admin">Administração</option>
                    </select>
                    <span class="ajuda-campo">
                        A administração vê catálogo, financeiro e auditoria. O vendedor vê apenas
                        a carteira dele.
                    </span>
                </div>

                <div x-show="papel === 'vendedor'" x-cloak>
                    <label for="comissao_pct" class="rotulo-campo">Comissão</label>
                    <div class="flex items-center gap-2">
                        <input id="comissao_pct" name="comissao_pct" type="number" min="0" max="50" required
                               class="campo-linha w-24 text-right"
                               value="{{ old('comissao_pct', $membro->comissao_pct ?? 10) }}">
                        <span class="text-sm text-gray-500 dark:text-gray-400">% do lucro</span>
                    </div>
                    <span class="ajuda-campo">
                        Vale a partir do próximo fechamento. Competência já fechada guarda o
                        percentual usado na emissão.
                    </span>
                    @error('comissao_pct') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div x-show="papel === 'admin'" x-cloak class="sm:col-span-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="pode_financeiro" value="0">
                        <input type="checkbox" name="pode_financeiro" value="1"
                               class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('pode_financeiro', $membro->pode_financeiro ?? false))>
                        Pode confirmar pagamentos e fechar competências
                    </label>
                    <span class="ajuda-campo">
                        Confirmar um pagamento libera a comissão do vendedor na hora, mesmo que nada
                        tenha entrado em conta. Conceda a quem confere o extrato.
                    </span>
                </div>

                <div class="flex items-center sm:col-span-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="ativo" value="0">
                        <input type="checkbox" name="ativo" value="1"
                               class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('ativo', $membro->ativo ?? true))>
                        Acesso liberado
                    </label>
                </div>
            </div>

            <div class="mt-8 flex gap-3">
                <x-avalia.botao>{{ $membro->exists ? 'Salvar' : 'Cadastrar' }}</x-avalia.botao>
                <x-avalia.botao variante="secundario" :href="route('equipe.index')">Cancelar</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
