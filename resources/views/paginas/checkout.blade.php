@extends('layouts.fullscreen-layout', ['title' => $oferta->titulo])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-white/90">
        <header class="relative border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex h-[60px] w-full max-w-4xl items-center justify-between px-6">
                <span class="inline-flex items-center gap-2.5">
                    <x-avalia.logotipo :tamanho="30" texto="1.15rem" marca="cobranca" />
                    <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
                </span>
                {{-- Quem vende e o produtor; a Avalia Gestor processa. Dizer isso
                     aqui evita a duvida mais cara do checkout: "quem esta
                     cobrando de mim?" --}}
                <span class="hidden pr-12 text-sm text-gray-500 sm:inline sm:pr-14 dark:text-gray-400">
                    Venda de {{ $oferta->produto->produtor->nome }}
                </span>
            </div>

            {{-- Na ponta extrema, fora do alinhamento das colunas, como na
                 pagina inicial: e ferramenta da pagina, e nao passo do funil.
                 O respiro a direita do nav reserva o lugar dele. --}}
            <x-avalia.tema class="absolute top-1/2 right-3 size-11 -translate-y-1/2 sm:right-4" />
        </header>

        <main class="mx-auto w-full max-w-4xl px-6 py-10">
            <div class="cartao p-6 lg:p-8">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $oferta->titulo }}</h1>
                <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $oferta->produto->nome }}</p>

                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Valor total</dt>
                        <dd class="text-lg font-semibold">{{ Dinheiro::brl($oferta->valor_cents) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Entrada</dt>
                        <dd class="text-lg font-semibold">{{ Dinheiro::brl($oferta->entrada_cents) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Parcelas</dt>
                        <dd class="text-lg font-semibold">
                            {{ $oferta->parcelas }}x de {{ Dinheiro::brl($oferta->valorDaParcela()) }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
                    Sem juros. O boleto da entrada vence em {{ $oferta->entrada_em_dias }} dias, e as parcelas
                    seguem no dia que você escolher.
                </p>
            </div>

            @if ($errors->any())
                <div class="aviso aviso-erro mt-6">Confira os campos marcados abaixo.</div>
            @endif

            <form method="POST" action="{{ route('checkout.fechar', $oferta->slug) }}" class="mt-6 grid gap-6">
                @csrf
                <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <section class="cartao p-6">
                    <h2 class="font-semibold">1. Seus dados</h2>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="nome" class="rotulo-campo">Nome completo</label>
                            <input id="nome" name="nome" type="text" class="campo" required value="{{ old('nome') }}">
                            @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="documento" class="rotulo-campo">CPF</label>
                            <input id="documento" name="documento" type="text" class="campo" required value="{{ old('documento') }}">
                            @error('documento') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="nascimento" class="rotulo-campo">Data de nascimento</label>
                            <input id="nascimento" name="nascimento" type="date" class="campo" required value="{{ old('nascimento') }}">
                            @error('nascimento') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="email" class="rotulo-campo">E-mail</label>
                            <input id="email" name="email" type="email" class="campo" required value="{{ old('email') }}">
                            @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="telefone" class="rotulo-campo">WhatsApp</label>
                            <input id="telefone" name="telefone" type="text" class="campo" required value="{{ old('telefone') }}">
                            @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </section>

                <section class="cartao p-6">
                    <h2 class="font-semibold">2. Endereço</h2>

                    <div class="mt-4 grid gap-5 sm:grid-cols-6">
                        <div class="sm:col-span-2">
                            <label for="cep" class="rotulo-campo">CEP</label>
                            <input id="cep" name="cep" type="text" class="campo" required value="{{ old('cep') }}">
                            @error('cep') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="logradouro" class="rotulo-campo">Rua</label>
                            <input id="logradouro" name="logradouro" type="text" class="campo" required value="{{ old('logradouro') }}">
                            @error('logradouro') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="numero" class="rotulo-campo">Número</label>
                            <input id="numero" name="numero" type="text" class="campo" required value="{{ old('numero') }}">
                            @error('numero') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="bairro" class="rotulo-campo">Bairro</label>
                            <input id="bairro" name="bairro" type="text" class="campo" required value="{{ old('bairro') }}">
                            @error('bairro') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="cidade" class="rotulo-campo">Cidade</label>
                            <input id="cidade" name="cidade" type="text" class="campo" required value="{{ old('cidade') }}">
                            @error('cidade') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="uf" class="rotulo-campo">UF</label>
                            <input id="uf" name="uf" type="text" maxlength="2" class="campo" required value="{{ old('uf') }}">
                            @error('uf') <span class="erro-campo">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </section>

                <section class="cartao p-6">
                    <h2 class="font-semibold">3. Pagamento</h2>

                    <div class="mt-4 max-w-xs">
                        <label for="melhor_dia" class="rotulo-campo">Melhor dia para as parcelas</label>
                        <select id="melhor_dia" name="melhor_dia" class="campo" required>
                            <option value="">Escolha um dia</option>
                            @for ($dia = 1; $dia <= 28; $dia++)
                                <option value="{{ $dia }}" @selected((int) old('melhor_dia') === $dia)>Dia {{ $dia }}</option>
                            @endfor
                        </select>
                        <span class="ajuda-campo">Até o dia 28, que existe em todo mês.</span>
                        @error('melhor_dia') <span class="erro-campo">{{ $message }}</span> @enderror
                    </div>

                    {{-- O aceite nasce desmarcado. Caixa pre-marcada nao prova
                         concordancia, e e a primeira coisa que se pergunta
                         quando um contrato e questionado. --}}
                    <label class="mt-6 flex items-start gap-3 text-sm">
                        <input type="checkbox" name="aceite" value="1" class="mt-0.5 size-4 rounded border-gray-300 accent-brand-500 dark:border-gray-600" required>
                        <span class="text-gray-600 dark:text-gray-300">
                            Li e aceito as condições desta compra: {{ Dinheiro::brl($oferta->valor_cents) }} no total,
                            com entrada de {{ Dinheiro::brl($oferta->entrada_cents) }} e
                            {{ $oferta->parcelas }} parcelas de {{ Dinheiro::brl($oferta->valorDaParcela()) }},
                            cobradas em boleto. Posso desistir em até
                            {{ $oferta->produto->dias_arrependimento }} dias e receber a entrada de volta.
                        </span>
                    </label>
                    @error('aceite') <span class="erro-campo">{{ $message }}</span> @enderror

                    <button type="submit" class="botao botao-primario mt-6 w-full sm:w-auto">
                        Fechar compra e gerar a entrada
                    </button>
                </section>
            </form>

            <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-500">
                Cobrança processada pela {{ \App\Support\Empresa::marcaCobranca() }}. Seus dados são usados para esta compra e para a cobrança das parcelas.
            </p>
        </main>
    </div>
@endsection
