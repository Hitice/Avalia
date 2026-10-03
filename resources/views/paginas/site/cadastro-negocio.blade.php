@extends('layouts.site', [
    'titulo' => 'Cadastro do seu negócio',
    'descricao' => 'Preencha os dados do seu negócio para publicarmos seu perfil no Google e nos canais de busca.',
    'secao' => 'cadastro-negocio',
    'semCabecalho' => true,
])

@section('content')
    {{-- Feito para o telefone: uma coluna, campos de 44px, teclado certo em
         cada campo e o CEP preenchendo o endereco. So o primeiro bloco e
         obrigatorio. --}}
    <section class="mx-auto w-full max-w-xl px-4 py-10 sm:px-6 sm:py-14">
        <x-avalia.logotipo :tamanho="32" texto="1.25rem" class="mb-8" />
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">Cadastro do seu negócio</h1>
        <p class="mt-2 text-gray-600">Com isto publicamos seu perfil no Google. Só o primeiro bloco é obrigatório.</p>

        @if (session('ok'))
            <div class="cartao mt-8 p-6 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-success-50 text-success-600">
                    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                </div>
                <h2 class="mt-4 text-xl font-semibold text-gray-900">Recebido</h2>
                <p class="mt-2 text-gray-600">{{ session('ok') }}</p>
            </div>
        @else
            @if ($errors->any())
                <p class="aviso aviso-erro mt-6">Confira os campos marcados.</p>
            @endif

            <form method="POST" action="{{ route('cadastro-negocio.enviar') }}" class="mt-8 grid gap-6"
                  x-data="{
                      async cep(valor) {
                          const d = valor.replace(/\D/g, '');
                          if (d.length !== 8) return;
                          try {
                              const r = await (await fetch('https://viacep.com.br/ws/' + d + '/json/')).json();
                              if (r.erro) return;
                              for (const [campo, chave] of [['logradouro', 'logradouro'], ['bairro', 'bairro'], ['cidade', 'localidade'], ['uf', 'uf']]) {
                                  const el = this.$refs[campo];
                                  if (el && ! el.value) el.value = r[chave] || '';
                              }
                              this.$refs.numero?.focus();
                          } catch {}
                      }
                  }">
                @csrf
                <input type="hidden" name="origem" value="{{ $origem }}">
                <input type="text" name="assunto" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div class="cartao grid gap-5 p-5 sm:p-6">
                    <h2 class="rotulo-grupo">Seu negócio</h2>

                    <div>
                        <label for="nome" class="rotulo-campo">Nome do negócio</label>
                        <input id="nome" name="nome" type="text" class="campo" required maxlength="150" autocomplete="organization"
                               value="{{ old('nome') }}" placeholder="Como está na fachada">
                        @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="categoria" class="rotulo-campo">O que vocês fazem</label>
                        <input id="categoria" name="categoria" type="text" class="campo" maxlength="120"
                               value="{{ old('categoria') }}" placeholder="Pizzaria, barbearia, pet shop">
                    </div>

                    <div>
                        <label for="responsavel" class="rotulo-campo">Quem responde</label>
                        <input id="responsavel" name="responsavel" type="text" class="campo" required maxlength="150" autocomplete="name"
                               value="{{ old('responsavel') }}">
                        @error('responsavel') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="whatsapp" class="rotulo-campo">WhatsApp</label>
                        <input id="whatsapp" name="whatsapp" type="tel" inputmode="tel" class="campo" required maxlength="20" autocomplete="tel"
                               value="{{ old('whatsapp') }}" placeholder="(00) 00000-0000">
                        @error('whatsapp') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="email" class="rotulo-campo">E-mail</label>
                        <input id="email" name="email" type="email" inputmode="email" class="campo" required maxlength="150" autocomplete="email"
                               value="{{ old('email') }}" placeholder="voce@empresa.com.br">
                        <span class="ajuda-campo">O Google envia o acesso ao perfil para este e-mail.</span>
                        @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="cartao grid gap-5 p-5 sm:p-6">
                    <h2 class="rotulo-grupo">Endereço</h2>

                    <label class="flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="atende_no_endereco" value="1" class="size-5 rounded border-gray-300" @checked(old('atende_no_endereco', true))>
                        Os clientes vêm até o nosso endereço
                    </label>
                    <span class="ajuda-campo -mt-3">Desmarque se vocês vão até o cliente: o endereço não é publicado.</span>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="cep" class="rotulo-campo">CEP</label>
                            <input id="cep" name="cep" type="text" inputmode="numeric" class="campo" maxlength="9" autocomplete="postal-code"
                                   value="{{ old('cep') }}" x-on:change="cep($event.target.value)">
                        </div>
                        <div class="col-span-2">
                            <label for="logradouro" class="rotulo-campo">Rua</label>
                            <input id="logradouro" name="logradouro" type="text" class="campo" maxlength="180" x-ref="logradouro"
                                   value="{{ old('logradouro') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="numero" class="rotulo-campo">Número</label>
                            <input id="numero" name="numero" type="text" inputmode="numeric" class="campo" maxlength="20" x-ref="numero"
                                   value="{{ old('numero') }}">
                        </div>
                        <div class="col-span-2">
                            <label for="complemento" class="rotulo-campo">Complemento</label>
                            <input id="complemento" name="complemento" type="text" class="campo" maxlength="120"
                                   value="{{ old('complemento') }}">
                        </div>
                    </div>

                    <div>
                        <label for="bairro" class="rotulo-campo">Bairro</label>
                        <input id="bairro" name="bairro" type="text" class="campo" maxlength="120" x-ref="bairro"
                               value="{{ old('bairro') }}">
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label for="cidade" class="rotulo-campo">Cidade</label>
                            <input id="cidade" name="cidade" type="text" class="campo" maxlength="120" x-ref="cidade"
                                   value="{{ old('cidade') }}">
                        </div>
                        <div>
                            <label for="uf" class="rotulo-campo">UF</label>
                            <input id="uf" name="uf" type="text" class="campo uppercase" maxlength="2" x-ref="uf"
                                   value="{{ old('uf') }}">
                            @error('uf') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="cartao grid gap-5 p-5 sm:p-6">
                    <h2 class="rotulo-grupo">Para completar o perfil</h2>

                    <div>
                        <label for="telefone" class="rotulo-campo">Telefone fixo</label>
                        <input id="telefone" name="telefone" type="tel" inputmode="tel" class="campo" maxlength="20"
                               value="{{ old('telefone') }}">
                    </div>

                    <div>
                        <label for="instagram" class="rotulo-campo">Instagram</label>
                        <input id="instagram" name="instagram" type="text" class="campo" maxlength="120"
                               value="{{ old('instagram') }}" placeholder="@seunegocio">
                    </div>

                    <div>
                        <label for="site" class="rotulo-campo">Site</label>
                        <input id="site" name="site" type="url" inputmode="url" class="campo" maxlength="255"
                               value="{{ old('site') }}" placeholder="https://">
                    </div>

                    <div>
                        <label for="documento" class="rotulo-campo">CNPJ ou CPF</label>
                        <input id="documento" name="documento" type="text" inputmode="numeric" class="campo" maxlength="20"
                               value="{{ old('documento') }}">
                    </div>

                    <div>
                        <label for="horarios" class="rotulo-campo">Horários</label>
                        <textarea id="horarios" name="horarios" class="campo" rows="3" maxlength="600"
                                  placeholder="Seg a sex 9h às 18h, sáb 9h às 13h">{{ old('horarios') }}</textarea>
                    </div>

                    <div>
                        <label for="descricao" class="rotulo-campo">Descrição</label>
                        <textarea id="descricao" name="descricao" class="campo" rows="4" maxlength="1500"
                                  placeholder="O que vocês vendem">{{ old('descricao') }}</textarea>
                    </div>
                </div>

                <button type="submit" class="botao botao-primario w-full sm:w-auto sm:self-start">Enviar cadastro</button>
            </form>
        @endif
    </section>
@endsection
