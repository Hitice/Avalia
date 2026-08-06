@extends('layouts.fullscreen-layout', ['title' => 'Definir senha'])

@section('content')
    <div class="grade-viva flex min-h-screen items-center justify-center bg-white p-6 dark:bg-gray-900">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
            <x-avalia.logotipo :tamanho="32" one class="mb-6" />

            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Defina sua senha</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Olá, {{ $nome }}. Escolha a senha do seu acesso à Avalia.
            </p>

            <form method="POST" action="{{ $destino }}" class="mt-6 space-y-5" autocomplete="on">
                @csrf

                <div>
                    <label for="senha" class="rotulo-campo">Nova senha</label>
                    <input id="senha" name="senha" type="password" class="campo" required
                           minlength="10" autocomplete="new-password" autofocus>
                    <span class="ajuda-campo">Pelo menos 10 caracteres. Misture palavras, números, o que só você lembra.</span>
                    @error('senha') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="senha_confirmation" class="rotulo-campo">Repita a senha</label>
                    <input id="senha_confirmation" name="senha_confirmation" type="password" class="campo" required
                           minlength="10" autocomplete="new-password">
                </div>

                <x-avalia.botao class="w-full">Definir senha e continuar</x-avalia.botao>
            </form>
        </div>
    </div>
@endsection
