@extends('layouts.app', ['title' => 'Consultar'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Nova consulta</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $empresa->razao_social }}</p>
        </div>
        <x-avalia.ajuda assunto="Consulta">Falar com a Avalia</x-avalia.ajuda>
    </div>

    @if (! $empresa->podeConsultar())
        <div class="aviso aviso-alerta mb-6">{{ $empresa->motivoSuspensao() }}</div>
    @endif

    @if ($pendentes->isNotEmpty())
        <div class="aviso aviso-alerta mb-6">
            As consultas ficam bloqueadas até o aceite dos documentos pendentes.
            <a class="font-medium underline" href="{{ route('empresa.documentos') }}">Ver documentos</a>
        </div>
    @endif

    @if ($servicos->isEmpty())
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Nenhum serviço liberado para o seu plano. Fale com a sua equipe comercial.
            </p>
        </div>
    @else
        <div class="cartao p-6">
            <p class="ajuda-campo mb-5">
                A finalidade fica registrada junto da consulta. Ela é o que sustenta o uso do dado
                se alguém perguntar depois por que aquele documento foi consultado.
            </p>

            <form method="POST" action="{{ route('empresa.consultas.executar') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf

                <div>
                    <label for="servico_id" class="rotulo-campo">Serviço</label>
                    <select id="servico_id" name="servico_id" class="campo" required>
                        @foreach ($servicos as $servico)
                            <option value="{{ $servico->id }}" @selected(old('servico_id') == $servico->id)>
                                {{ $servico->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="documento" class="rotulo-campo">CPF ou CNPJ</label>
                    <input id="documento" name="documento" type="text" class="campo" required
                           inputmode="numeric" value="{{ old('documento') }}">
                    @error('documento') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="finalidade" class="rotulo-campo">Finalidade</label>
                    <input id="finalidade" name="finalidade" type="text" class="campo" required
                           maxlength="120" value="{{ old('finalidade') }}"
                           placeholder="Análise de crédito para venda a prazo">
                    @error('finalidade') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="solicitante" class="rotulo-campo">Quem está solicitando</label>
                    <input id="solicitante" name="solicitante" type="text" class="campo"
                           maxlength="150" value="{{ old('solicitante') }}">
                    <span class="ajuda-campo">Opcional, e útil quando várias pessoas usam o mesmo acesso.</span>
                </div>

                <div class="sm:col-span-2">
                    <x-avalia.botao>Consultar</x-avalia.botao>
                </div>
            </form>
        </div>
    @endif
@endsection
