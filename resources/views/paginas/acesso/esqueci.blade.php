@extends('layouts.fullscreen-layout', ['title' => 'Esqueci minha senha'])

@section('content')
    <div class="grade-viva flex min-h-screen items-center justify-center bg-white p-6 dark:bg-gray-900">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-5">
                <x-avalia.botao variante="secundario" tamanho="sm" :href="route('entrar')">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Voltar
                </x-avalia.botao>
            </div>
            <x-avalia.logotipo :tamanho="32" one class="mb-6" />

            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Esqueci minha senha</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Informe o e-mail do seu acesso. Se ele estiver cadastrado, enviamos
                um link para você definir uma senha nova.
            </p>

            @if (session('ok'))
                <div class="aviso aviso-sucesso mt-5">{{ session('ok') }}</div>
            @endif

            <form method="POST" action="{{ route('senha.esqueci.enviar') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="rotulo-campo">E-mail</label>
                    <input id="email" name="email" type="email" class="campo" required autofocus
                           value="{{ old('email') }}" placeholder="voce@empresa.com.br">
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <x-avalia.botao class="w-full">Enviar link de redefinição</x-avalia.botao>
            </form>
        </div>
    </div>
@endsection
