@extends('layouts.fullscreen-layout', ['title' => 'Crie sua conta'])

@section('content')
    <div class="relative flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12 dark:bg-gray-950">
        {{-- Sem topo nesta tela, o botao fica na mesma ponta que ocuparia
             se houvesse: a distancia da borda e a mesma das outras. --}}
        <x-avalia.tema class="absolute top-4 right-3 size-11 sm:right-4" />

        <div class="w-full max-w-md">
            <a href="{{ route('cobranca') }}" class="mb-8 flex items-center justify-center gap-2.5" aria-label="{{ \App\Support\Empresa::marcaCobranca() }}">
                <x-avalia.logotipo :tamanho="36" texto="1.35rem" marca="cobranca" />
                <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
            </a>

            <div class="cartao p-6 lg:p-8">
                <h1 class="text-2xl font-semibold tracking-tight">Crie sua conta</h1>
                <p class="mt-3 text-gray-500 dark:text-gray-400">
                    Você está a um passo de alavancar os resultados do seu lançamento!
                </p>
                <p class="mt-1 text-gray-500 dark:text-gray-400">
                    Quatro campos e você já entra. Os dados de recebimento a equipe pede depois,
                    junto da aprovação.
                </p>

                <form method="POST" action="{{ route('produtor.cadastrar') }}" class="mt-6 grid gap-5">
                    @csrf
                    <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                    <div>
                        <label for="nome" class="rotulo-campo">Nome completo</label>
                        <input id="nome" name="nome" type="text" class="campo" required autofocus
                               autocomplete="name" value="{{ old('nome') }}">
                        @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="email" class="rotulo-campo">E-mail</label>
                        <input id="email" name="email" type="email" class="campo" required
                               autocomplete="email" value="{{ old('email') }}">
                        @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="whatsapp" class="rotulo-campo">WhatsApp</label>
                        <input id="whatsapp" name="whatsapp" type="tel" class="campo" required
                               autocomplete="tel" placeholder="(34) 99999-9999" value="{{ old('whatsapp') }}">
                        @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    {{-- Mostrar o que foi digitado resolve o mesmo que um campo
                         de confirmacao, sem pedir a senha duas vezes. Quem erra
                         a senha no cadastro so descobre no proximo login, e ai
                         ja saiu da tela. --}}
                    <div x-data="{ vendo: false }">
                        <label for="senha" class="rotulo-campo">Senha</label>

                        <div class="relative">
                            <input id="senha" name="senha" class="campo pr-11" required
                                   autocomplete="new-password" :type="vendo ? 'text' : 'password'">

                            <button type="button" @click="vendo = ! vendo"
                                    :aria-label="vendo ? 'Ocultar senha' : 'Mostrar senha'"
                                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200">
                                <svg x-show="! vendo" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.9 5.5 12 5.5 21.5 12 21.5 12 18.1 18.5 12 18.5 2.5 12 2.5 12z"/>
                                </svg>
                                <svg x-show="vendo" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 5.7A9.8 9.8 0 0112 5.5c6.1 0 9.5 6.5 9.5 6.5a17 17 0 01-3.4 4.3M6.3 7.8A17 17 0 002.5 12S5.9 18.5 12 18.5c1 0 1.9-.2 2.7-.5"/>
                                </svg>
                            </button>
                        </div>

                        <span class="ajuda-campo">Pelo menos 8 caracteres.</span>
                        @error('senha') <span class="erro-campo">{{ $message }}</span> @enderror
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
