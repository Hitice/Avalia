@extends('layouts.fullscreen-layout', ['title' => 'Crie sua conta'])

@section('content')
    <div class="relative flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12 dark:bg-gray-950">
        {{-- Sem topo nesta tela, o botao fica na mesma ponta que ocuparia
             se houvesse: a distancia da borda e a mesma das outras. --}}
        <x-avalia.tema class="absolute top-4 right-3 size-11 sm:right-4" />

        <div class="w-full max-w-md">
            <a href="{{ route('cobranca') }}" class="mb-8 flex items-center justify-center gap-2.5" aria-label="Avalia 360">
                <x-avalia.logotipo :tamanho="36" texto="1.35rem" />
                <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
            </a>

            <div class="cartao p-6 lg:p-8">
                <h1 class="text-2xl font-semibold tracking-tight">Crie sua conta</h1>
                <p class="mt-3 text-gray-500 dark:text-gray-400">
                    Você está a um passo de alavancar os resultados do seu lançamento!
                </p>
                <p class="mt-1 text-gray-500 dark:text-gray-400">
                    Cadastre-se abaixo para criar a sua conta e dar início a uma parceria de sucesso:
                </p>

                <form method="POST" action="{{ route('produtor.cadastrar') }}" class="mt-6 grid gap-5">
                    @csrf
                    <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                    <div>
                        <label for="nome" class="rotulo-campo">Nome completo</label>
                        <input id="nome" name="nome" type="text" class="campo" required autofocus value="{{ old('nome') }}">
                        @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="documento" class="rotulo-campo">CPF ou CNPJ</label>
                        <input id="documento" name="documento" type="text" class="campo" required value="{{ old('documento') }}">
                        @error('documento') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="email" class="rotulo-campo">E-mail</label>
                        <input id="email" name="email" type="email" class="campo" required value="{{ old('email') }}">
                        @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="whatsapp" class="rotulo-campo">WhatsApp</label>
                        <input id="whatsapp" name="whatsapp" type="text" class="campo" required
                               placeholder="(34) 99999-9999" value="{{ old('whatsapp') }}">
                        @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="senha" class="rotulo-campo">Senha</label>
                        <input id="senha" name="senha" type="password" class="campo" required autocomplete="new-password">
                        <span class="ajuda-campo">Pelo menos 8 caracteres.</span>
                        @error('senha') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="senha_confirmation" class="rotulo-campo">Repita a senha</label>
                        <input id="senha_confirmation" name="senha_confirmation" type="password" class="campo" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="botao botao-primario w-full">Cadastrar</button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Já tem conta?
                <a href="{{ route('produtor.entrar') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Clique aqui.</a>
            </p>
        </div>
    </div>
@endsection
