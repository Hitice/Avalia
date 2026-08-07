@extends('layouts.app', ['title' => 'Minha conta'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Minha conta</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $conta->nome ?? $conta->razao_social }} · {{ $conta->email }}</p>
    </div>

    @if (session('ok'))
        <div class="aviso aviso-ok mb-6">{{ session('ok') }}</div>
    @endif

    <div class="cartao max-w-xl p-6">
        <h2 class="font-medium text-gray-800 dark:text-white/90">Trocar a senha</h2>
        <p class="ajuda-campo mt-1 mb-5">
            Ao trocar, as outras sessões desta conta são encerradas. Esta continua aberta.
        </p>

        <form method="POST" action="{{ route('perfil.senha') }}" class="grid gap-5" autocomplete="on">
            @csrf

            <div>
                <label for="senha_atual" class="rotulo-campo">Senha atual</label>
                <input id="senha_atual" name="senha_atual" type="password" class="campo" required
                       autocomplete="current-password">
                @error('senha_atual') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="senha" class="rotulo-campo">Nova senha</label>
                <input id="senha" name="senha" type="password" class="campo" required
                       minlength="10" autocomplete="new-password">
                <span class="ajuda-campo">Pelo menos 10 caracteres.</span>
                @error('senha') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="senha_confirmation" class="rotulo-campo">Repita a nova senha</label>
                <input id="senha_confirmation" name="senha_confirmation" type="password" class="campo" required
                       minlength="10" autocomplete="new-password">
            </div>

            <div>
                <x-avalia.botao>Trocar senha</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
