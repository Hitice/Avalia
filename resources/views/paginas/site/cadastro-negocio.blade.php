@extends('layouts.site', [
    'titulo' => 'Cadastro do seu negócio',
    'descricao' => 'Preencha os dados do seu negócio para publicarmos seu perfil no Google e nos canais de busca.',
    'secao' => 'cadastro-negocio',
])

@section('content')
    <section class="mx-auto w-full max-w-3xl px-6 py-16">
        <h1 class="text-3xl font-semibold text-gray-900">Cadastro do seu negócio</h1>

        <p class="mt-3 text-gray-600">
            Com estes dados a gente publica seu perfil no Google e deixa sua loja aparecendo
            na busca e no mapa. Só o começo é obrigatório; o resto você pode deixar em branco
            que a gente confirma por WhatsApp.
        </p>

        @if (session('ok'))
            <p class="aviso aviso-ok mt-6">{{ session('ok') }}</p>
        @endif

        @if ($errors->any())
            <p class="aviso aviso-erro mt-6">Confira os campos marcados abaixo.</p>
        @endif

        <form method="POST" action="{{ route('cadastro-negocio.enviar') }}" class="mt-8 grid gap-6">
            @csrf
            <input type="hidden" name="origem" value="{{ $origem }}">

            {{-- Armadilha: ninguém vê, robô preenche. --}}
            <input type="text" name="assunto" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nome" class="rotulo-campo">Nome do negócio</label>
                    <input id="nome" name="nome" type="text" class="campo" required maxlength="150"
                           value="{{ old('nome') }}" placeholder="Como está na fachada">
                    @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="categoria" class="rotulo-campo">O que vocês fazem</label>
                    <input id="categoria" name="categoria" type="text" class="campo" maxlength="120"
                           value="{{ old('categoria') }}" placeholder="Pizzaria, barbearia, pet shop…">
                    @error('categoria') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="responsavel" class="rotulo-campo">Quem responde pelo negócio</label>
                    <input id="responsavel" name="responsavel" type="text" class="campo" required maxlength="150"
                           value="{{ old('responsavel') }}">
                    @error('responsavel') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="whatsapp" class="rotulo-campo">WhatsApp</label>
                    <input id="whatsapp" name="whatsapp" type="tel" class="campo" required maxlength="20"
                           value="{{ old('whatsapp') }}" placeholder="(00) 00000-0000">
                    @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="email" class="rotulo-campo">E-mail</label>
                    <input id="email" name="email" type="email" class="campo" required maxlength="150"
                           value="{{ old('email') }}">
                    <span class="ajuda-campo">É por aqui que o Google envia o acesso ao perfil.</span>
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>
            </div>

            <fieldset class="grid gap-4 border-t border-gray-200 pt-6">
                <legend class="rotulo-grupo">Onde vocês atendem</legend>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="atende_no_endereco" value="1" class="size-4"
                           @checked(old('atende_no_endereco', true))>
                    Os clientes vêm até o nosso endereço
                </label>

                <span class="ajuda-campo">
                    Desmarque se vocês vão até o cliente. O Google não publica o endereço nesse caso,
                    e é o que evita expor endereço residencial de quem atende em casa.
                </span>

                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <label for="cep" class="rotulo-campo">CEP</label>
                        <input id="cep" name="cep" type="text" class="campo" maxlength="9" value="{{ old('cep') }}">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="logradouro" class="rotulo-campo">Rua</label>
                        <input id="logradouro" name="logradouro" type="text" class="campo" maxlength="180"
                               value="{{ old('logradouro') }}">
                    </div>
                    <div>
                        <label for="numero" class="rotulo-campo">Número</label>
                        <input id="numero" name="numero" type="text" class="campo" maxlength="20"
                               value="{{ old('numero') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="complemento" class="rotulo-campo">Complemento</label>
                        <input id="complemento" name="complemento" type="text" class="campo" maxlength="120"
                               value="{{ old('complemento') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="bairro" class="rotulo-campo">Bairro</label>
                        <input id="bairro" name="bairro" type="text" class="campo" maxlength="120"
                               value="{{ old('bairro') }}">
                    </div>
                    <div>
                        <label for="cidade" class="rotulo-campo">Cidade</label>
                        <input id="cidade" name="cidade" type="text" class="campo" maxlength="120"
                               value="{{ old('cidade') }}">
                    </div>
                    <div>
                        <label for="uf" class="rotulo-campo">UF</label>
                        <input id="uf" name="uf" type="text" class="campo" maxlength="2" value="{{ old('uf') }}">
                        @error('uf') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="grid gap-4 border-t border-gray-200 pt-6">
                <legend class="rotulo-grupo">Para o perfil ficar completo</legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="telefone" class="rotulo-campo">Telefone fixo</label>
                        <input id="telefone" name="telefone" type="tel" class="campo" maxlength="20"
                               value="{{ old('telefone') }}">
                    </div>
                    <div>
                        <label for="site" class="rotulo-campo">Site</label>
                        <input id="site" name="site" type="text" class="campo" maxlength="255"
                               value="{{ old('site') }}" placeholder="Se tiver">
                    </div>
                    <div>
                        <label for="instagram" class="rotulo-campo">Instagram</label>
                        <input id="instagram" name="instagram" type="text" class="campo" maxlength="120"
                               value="{{ old('instagram') }}" placeholder="@seunegocio">
                    </div>
                    <div>
                        <label for="documento" class="rotulo-campo">CNPJ ou CPF</label>
                        <input id="documento" name="documento" type="text" class="campo" maxlength="20"
                               value="{{ old('documento') }}" placeholder="Se tiver">
                    </div>
                </div>

                <div>
                    <label for="horarios" class="rotulo-campo">Horários de funcionamento</label>
                    <textarea id="horarios" name="horarios" class="campo" rows="3" maxlength="600"
                              placeholder="Seg a sex 9h às 18h, sáb 9h às 13h, domingo fechado">{{ old('horarios') }}</textarea>
                </div>

                <div>
                    <label for="descricao" class="rotulo-campo">Uma descrição do negócio</label>
                    <textarea id="descricao" name="descricao" class="campo" rows="4" maxlength="1500"
                              placeholder="O que vocês vendem">{{ old('descricao') }}</textarea>
                </div>
            </fieldset>

            <div>
                <button type="submit" class="botao botao-primario">Enviar cadastro</button>
            </div>
        </form>
    </section>
@endsection
