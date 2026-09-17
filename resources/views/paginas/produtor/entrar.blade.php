@extends('layouts.fullscreen-layout', ['title' => 'Entrar'])

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12 dark:bg-gray-950">
        <div class="w-full max-w-md">
            <a href="{{ route('cobranca') }}" class="mb-8 flex items-center justify-center gap-2.5" aria-label="Avalia 360">
                <x-avalia.logotipo :tamanho="36" texto="1.35rem" />
                <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
            </a>

            <div class="cartao p-6 lg:p-8">
                <h1 class="text-2xl font-semibold tracking-tight">Entrar</h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Acesse o painel do produtor.</p>

                @if ($errors->any())
                    {{-- Uma mensagem so, igual para e-mail errado e senha
                         errada: separar as duas transforma a tela de login numa
                         consulta de quem tem conta aqui. --}}
                    <div class="aviso aviso-erro mt-5">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('produtor.entrar.enviar') }}" class="mt-6 grid gap-5">
                    @csrf

                    <div>
                        <label for="email" class="rotulo-campo">E-mail</label>
                        <input id="email" name="email" type="email" class="campo" required autofocus value="{{ old('email') }}">
                    </div>

                    <div>
                        <label for="senha" class="rotulo-campo">Senha</label>
                        <input id="senha" name="senha" type="password" class="campo" required autocomplete="current-password">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" name="lembrar" value="1" class="size-4 rounded border-gray-300 accent-brand-500 dark:border-gray-600">
                        Manter conectado
                    </label>

                    <button type="submit" class="botao botao-primario w-full">Entrar</button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Ainda não tem conta?
                <a href="{{ route('produtor.criar-conta') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Cadastre-se.</a>
            </p>
        </div>
    </div>
@endsection
