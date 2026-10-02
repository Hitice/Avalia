@extends('layouts.app', ['title' => 'Minha conta'])

@section('content')
    <x-avalia.cabecalho-pagina titulo="Minha conta">
        <x-slot:subtitulo>
            {{ $conta->nome ?? $conta->razao_social }} · {{ $conta->email }}
        </x-slot:subtitulo>
    </x-avalia.cabecalho-pagina>

    @if (session('ok'))
        <div class="aviso aviso-ok mb-6">{{ session('ok') }}</div>
    @endif

    <div class="cartao max-w-xl p-6">
        <h2 class="titulo-cartao">Trocar a senha</h2>
        <p class="ajuda-campo mt-1 mb-5">
            Ao trocar, as outras sessões desta conta são encerradas. Esta continua aberta.
        </p>

        <form method="POST" action="{{ route('perfil.senha') }}" class="grid gap-5" autocomplete="on">
            @csrf

            <div>
                <label for="senha_atual" class="rotulo-campo">Senha atual</label>
                <x-avalia.senha id="senha_atual" name="senha_atual" required
                       autocomplete="current-password" />
                @error('senha_atual') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="senha" class="rotulo-campo">Nova senha</label>
                <x-avalia.senha id="senha" name="senha" required
                       minlength="10" autocomplete="new-password" />
                <span class="ajuda-campo">Pelo menos 10 caracteres.</span>
                @error('senha') <span class="erro-campo">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="senha_confirmation" class="rotulo-campo">Repita a nova senha</label>
                <x-avalia.senha id="senha_confirmation" name="senha_confirmation" required
                       minlength="10" autocomplete="new-password" />
            </div>

            <div>
                <x-avalia.botao>Trocar senha</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
