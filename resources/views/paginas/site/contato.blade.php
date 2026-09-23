@extends('layouts.site', [
    'titulo' => 'Contato',
    'descricao' => 'Fale com a Avalia e receba uma proposta com escopo e investimento definidos.',
    'secao' => 'site.contato',
])

@php
    use App\Support\Empresa;
    use App\Support\Suporte;
@endphp

@section('content')
    <x-site.cabecalho selo="Contato" titulo="Fale conosco">
        Conte o que você quer automatizar ou desenvolver. Respondemos com uma proposta clara,
        com escopo e investimento definidos.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto grid w-full max-w-[87rem] gap-8 px-6 lg:grid-cols-[1.4fr_1fr]">
            {{-- O pedido entra no nosso banco.

                 A versao anterior desta pagina montava um mailto e um link de
                 WhatsApp com o que a pessoa digitou. Dado pessoal nao viaja em
                 URL de conversa: ela passa por servidor de terceiro e fica no
                 historico do navegador. Aqui o formulario grava, e a conversa
                 comeca do nosso lado. --}}
            <div class="cartao p-6 lg:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Envie sua mensagem</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Respondemos em horário comercial, pelo canal que você preferir.
                </p>

                @if (session('contato_ok'))
                    <p class="aviso aviso-ok mt-6">
                        Pedido recebido. Nossa equipe entra em contato pelo canal informado.
                    </p>
                @endif

                <form method="POST" action="{{ route('site.contato.enviar') }}" class="mt-6 grid gap-4">
                    @csrf

                    {{-- Campo que nenhuma pessoa ve nem preenche. Robo de
                         formulario preenche tudo, e valor aqui e lixo
                         automatizado. --}}
                    <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contato-nome" class="rotulo-campo">Nome</label>
                            <input id="contato-nome" name="nome" type="text" class="campo" required
                                   autocomplete="name" value="{{ old('nome') }}">
                            @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="contato-empresa" class="rotulo-campo">Empresa</label>
                            <input id="contato-empresa" name="empresa" type="text" class="campo" required
                                   autocomplete="organization" value="{{ old('empresa') }}">
                            @error('empresa') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contato-telefone" class="rotulo-campo">WhatsApp</label>
                            <input id="contato-telefone" name="telefone" type="text" class="campo" required
                                   autocomplete="tel" placeholder="(00) 00000-0000" value="{{ old('telefone') }}">
                            @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="contato-email" class="rotulo-campo">E-mail</label>
                            <input id="contato-email" name="email" type="email" class="campo" required
                                   autocomplete="email" value="{{ old('email') }}">
                            @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="contato-assunto" class="rotulo-campo">Assunto</label>
                        <select id="contato-assunto" name="assunto" class="campo" required>
                            @foreach ($assuntos as $assunto)
                                <option value="{{ $assunto }}" @selected(old('assunto') === $assunto)>{{ $assunto }}</option>
                            @endforeach
                        </select>
                        @error('assunto') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="contato-mensagem" class="rotulo-campo">Mensagem</label>
                        <textarea id="contato-mensagem" name="mensagem" rows="5" class="campo" required
                                  placeholder="Conte o que você quer automatizar ou desenvolver.">{{ old('mensagem') }}</textarea>
                        @error('mensagem') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <x-avalia.botao class="mt-2 w-full sm:w-auto">
                        Enviar pedido de contato
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </x-avalia.botao>

                    <p class="text-xs leading-relaxed text-gray-500">
                        Guardamos apenas o que você preencheu acima, para responder ao seu pedido.
                        Saiba mais na <a href="{{ route('site.privacidade') }}" class="text-brand-600 underline underline-offset-2">Política de privacidade</a>.
                    </p>
                </form>
            </div>

            <div class="space-y-4">
                <a href="{{ Suporte::whatsapp('Quero falar com a '.Empresa::marca()) }}" target="_blank" rel="noopener noreferrer"
                   class="cartao cartao-link flex items-start gap-4 p-6">
                    <span class="icone-caixa shrink-0">
                        <svg class="size-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23Z" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="font-semibold text-gray-900">WhatsApp</h2>
                        <p class="mt-1 text-sm text-gray-600">Fale com a equipe agora</p>
                    </div>
                </a>

                <a href="mailto:{{ Empresa::email() }}" class="cartao cartao-link flex items-start gap-4 p-6">
                    <span class="icone-caixa shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <rect x="3" y="5.5" width="18" height="13" rx="2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="font-semibold text-gray-900">E-mail</h2>
                        <p class="mt-1 text-sm break-all text-gray-600">{{ Empresa::email() }}</p>
                    </div>
                </a>

                <div class="cartao flex items-start gap-4 p-6">
                    <span class="icone-caixa shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="font-semibold text-gray-900">Matriz</h2>
                        <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ Empresa::endereco() }}</p>
                    </div>
                </div>

                <div class="cartao flex items-start gap-4 p-6">
                    <span class="icone-caixa shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ Empresa::bracoRotulo() }}</h2>
                        <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ Empresa::bracoEndereco() }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
