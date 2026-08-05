@extends('layouts.app', ['title' => $empresa->exists ? $empresa->razao_social : 'Nova empresa'])

@php
    use App\Support\Dinheiro;
    use App\Support\Documento;

    // Carteira e situacao sao decisoes da administracao. O vendedor nao ve os
    // campos, e o controller ignora os dois mesmo que venham forjados no POST.
    $ehAdmin = auth('staff')->user()?->ehAdmin();
@endphp

@section('content')
    <a href="{{ $ehAdmin ? ($empresa->exists ? route('empresas.ficha', $empresa) : route('empresas.index')) : route('carteira') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        {{ $ehAdmin ? ($empresa->exists ? $empresa->razao_social : 'Empresas') : 'Minha carteira' }}
    </a>

    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">
        {{ $empresa->exists ? 'Editar cadastro' : 'Nova empresa' }}
    </h1>

    @include('paginas.catalogo.avisos')

    <div class="cartao p-6">
        <form method="POST"
              action="{{ $empresa->exists ? route('empresas.atualizar', $empresa) : route('empresas.salvar') }}">
            @csrf
            @if ($empresa->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="razao_social" class="rotulo-campo">Razão social</label>
                    <input id="razao_social" name="razao_social" type="text" class="campo" required
                           value="{{ old('razao_social', $empresa->razao_social) }}">
                    @error('razao_social') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cnpj" class="rotulo-campo">CNPJ</label>
                    <input id="cnpj" name="cnpj" type="text" class="campo" required
                           value="{{ old('cnpj', Documento::formatarCnpj($empresa->cnpj)) }}"
                           placeholder="12.345.678/0001-95">
                    <span class="ajuda-campo">Aceita letra: o CNPJ alfanumérico vale desde julho de 2026.</span>
                    @error('cnpj') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div @class(['hidden' => ! $ehAdmin])>
                    <label for="situacao" class="rotulo-campo">Situação</label>
                    <select id="situacao" name="situacao" class="campo" required>
                        @foreach (['ativo' => 'Ativo', 'inadimplente' => 'Inadimplente', 'bloqueado' => 'Bloqueado', 'inativo' => 'Inativo'] as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('situacao', $empresa->situacao) === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Só ativo consulta. Inadimplente e bloqueado ainda entram para ver a fatura.</span>
                </div>

                <div>
                    <label for="email" class="rotulo-campo">E-mail de acesso</label>
                    <input id="email" name="email" type="email" class="campo" required
                           value="{{ old('email', $empresa->email) }}">
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="senha" class="rotulo-campo">Senha</label>
                    <input id="senha" name="senha" type="password" class="campo"
                           @required(! $empresa->exists) autocomplete="new-password">
                    @if ($empresa->exists)
                        <span class="ajuda-campo">Em branco mantém a senha atual.</span>
                    @endif
                    @error('senha') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="plano_id" class="rotulo-campo">Plano contratado</label>
                    <select id="plano_id" name="plano_id" class="campo">
                        <option value="">Sem plano</option>
                        @foreach ($planos as $plano)
                            <option value="{{ $plano->id }}" @selected(old('plano_id', $empresa->plano_id) == $plano->id)>
                                {{ $plano->nome }} · {{ Dinheiro::faixa($plano->consumo_minimo_cents) }}
                            </option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Define a coluna de preços que a consulta vai congelar.</span>
                </div>

                <div @class(['hidden' => ! $ehAdmin])>
                    <label for="vendedor_id" class="rotulo-campo">Vendedor</label>
                    <select id="vendedor_id" name="vendedor_id" class="campo">
                        <option value="">Sem carteira</option>
                        @foreach ($vendedores as $vendedor)
                            <option value="{{ $vendedor->id }}" @selected(old('vendedor_id', $empresa->vendedor_id) == $vendedor->id)>
                                {{ $vendedor->nome }}
                            </option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Quem recebe a comissão das faturas desta empresa.</span>
                </div>
            </div>

            <div class="mt-8 flex gap-3">
                <x-avalia.botao>{{ $empresa->exists ? 'Salvar' : 'Cadastrar' }}</x-avalia.botao>
                <x-avalia.botao variante="secundario"
                                :href="$ehAdmin ? ($empresa->exists ? route('empresas.ficha', $empresa) : route('empresas.index')) : route('carteira')">
                    Cancelar
                </x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
