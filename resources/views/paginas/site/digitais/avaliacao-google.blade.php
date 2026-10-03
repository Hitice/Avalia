@extends('layouts.site', [
    'titulo' => 'Gerador de link de avaliação do Google, grátis',
    'descricao' => 'Informe o nome do seu estabelecimento e receba da Avalia o link curto que leva o cliente direto ao formulário de avaliação do Google. Grátis.',
    'secao' => 'digitais.index',
])

@section('content')
    <x-site.cabecalho selo="Grátis" :titulo="$servico['titulo']" :icone="$servico['icone']">
        {{ $servico['resumo'] }}
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[52rem] px-6">

            @if (session('linkPronto'))
                <div class="cartao mb-8 p-7">
                    <p class="rotulo-grupo">{{ session('ok') }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a href="{{ session('linkPronto') }}" target="_blank" rel="noopener"
                           class="text-lg font-semibold text-brand-600">{{ session('linkPronto') }}</a>

                        <button type="button" class="botao botao-secundario botao-sm"
                                onclick="navigator.clipboard.writeText('{{ session('linkPronto') }}')">Copiar</button>
                    </div>

                    <p class="aviso aviso-alerta mt-3">
                        Confira o link antes de imprimir: abra e veja se cai no seu estabelecimento.
                        O gerador pode errar quando há nomes parecidos.
                    </p>
                    <p class="ajuda-campo mt-3">
                        Use no adesivo do balcão, no cardápio ou na plaquinha. O link conta quantas
                        pessoas abriram o pedido, e a gente entra em contato para acompanhar.
                    </p>
                </div>
            @endif

            @if (session('erro'))
                <p class="aviso aviso-erro mb-8">{{ session('erro') }}</p>
            @endif

            {{-- Mais de um homônimo: quem escolhe é o dono, porque link errado manda
                 a freguesia dele avaliar o concorrente. O contato viaja nos campos
                 escondidos para não ser pedido duas vezes. --}}
            @if (session('lugares'))
                <div class="cartao mb-8 p-7">
                    <p class="rotulo-grupo">Achamos mais de um com esse nome. Qual é o seu?</p>

                    <div class="mt-4 grid gap-2">
                        @foreach (session('lugares') as $lugar)
                            <form method="POST" action="{{ route('digitais.avaliacao.gerar') }}"
                                  class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                                @csrf
                                <input type="hidden" name="place_id" value="{{ $lugar['place_id'] }}">
                                <input type="hidden" name="nome" value="{{ $lugar['nome'] }}">
                                <input type="hidden" name="cidade" value="{{ old('cidade') }}">
                                <input type="hidden" name="whatsapp" value="{{ old('whatsapp') }}">
                                <input type="hidden" name="email" value="{{ old('email') }}">

                                <span>
                                    <span class="font-medium text-gray-900">{{ $lugar['nome'] }}</span>
                                    <span class="block text-sm text-gray-500">{{ $lugar['endereco'] }}</span>
                                </span>

                                <button type="submit" class="botao botao-secundario botao-sm">É este</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('digitais.avaliacao.buscar') }}" class="cartao grid gap-5 p-7">
                @csrf

                {{-- Ninguém vê, robô preenche. --}}
                <input type="text" name="assunto" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="av-nome" class="rotulo-campo">Nome do estabelecimento</label>
                        <input id="av-nome" name="nome" type="text" class="campo" required maxlength="150"
                               value="{{ old('nome') }}" placeholder="Como está no seu perfil do Google">
                        <p class="ajuda-campo">Igual ao perfil do Google.</p>
                        @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    {{-- So depois de nao achar: na primeira tentativa a cidade e ruido. --}}
                    @if (session('pedirCidade'))
                        <div class="sm:col-span-2">
                            <label for="av-cidade" class="rotulo-campo">Cidade</label>
                            <input id="av-cidade" name="cidade" type="text" class="campo" maxlength="120"
                                   value="{{ old('cidade') }}" autofocus placeholder="Para desempatar">
                        </div>
                    @endif

                    <div>
                        <label for="av-whatsapp" class="rotulo-campo">WhatsApp</label>
                        <input id="av-whatsapp" name="whatsapp" type="tel" class="campo" required maxlength="20"
                               value="{{ old('whatsapp') }}" placeholder="(00) 00000-0000">
                        @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="av-email" class="rotulo-campo">E-mail</label>
                        <input id="av-email" name="email" type="email" class="campo" required maxlength="150"
                               value="{{ old('email') }}">
                        @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>
                </div>

                <p class="ajuda-campo">
                    Pedimos contato porque a consulta ao Google é paga por busca, e é assim que a
                    ferramenta continua de graça para quem usa de verdade. Guardamos seu link para
                    você pedir de novo sem recomeçar.
                </p>

                <div>
                    <button type="submit" class="botao botao-primario">Gerar meu link</button>
                </div>
            </form>
        </div>
    </section>
@endsection
