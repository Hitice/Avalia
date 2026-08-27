@extends('layouts.app', ['title' => $lead->nome])

@php
    use App\Enums\SituacaoLead;
    use App\Support\Documento;

    $falta = $lead->faltaParaVirarCliente();
@endphp

@section('content')
    <a href="{{ route('carteira.leads') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Leads
    </a>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $lead->nome }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="etiqueta {{ $lead->situacao->etiqueta() }}">{{ $lead->situacao->rotulo() }}</span>
                @if ($lead->cidadeRotulo())
                    <span>{{ $lead->cidadeRotulo() }}</span>
                @endif
            </p>
        </div>

        @if ($lead->jaEhCliente())
            <span class="etiqueta etiqueta-sucesso">
                Virou cliente em {{ $lead->convertido_em?->format('d/m/Y') }}
            </span>
        @else
            {{-- Fora do formulario principal: form dentro de form nao existe em
                 HTML. E um GET, porque converter nao grava nada: abre o cadastro
                 de cliente ja preenchido por esta ficha. --}}
            <x-avalia.botao :href="route('carteira.leads.converter', $lead)"
                            class="{{ $falta ? 'pointer-events-none opacity-40' : '' }}"
                            :aria-disabled="$falta ? 'true' : null">
                Converter em cliente
            </x-avalia.botao>
        @endif
    </div>

    @include('paginas.catalogo.avisos')

    @if ($falta && ! $lead->jaEhCliente())
        {{-- Botao desabilitado sem explicacao vira chamado no atendimento: a
             tela diz o que falta, e os campos estao logo abaixo. --}}
        <div class="aviso aviso-alerta mb-6">
            Para abrir o cadastro de cliente falta preencher <strong>{{ implode(' e ', $falta) }}</strong>.
            São os dois campos que a empresa precisa ter: o CNPJ é quem vai ser cobrado, e o e-mail
            é o acesso dela à plataforma.
        </div>
    @endif

    @if ($errors->any())
        <div class="aviso aviso-erro mb-6">
            <p class="font-medium">Confira os campos destacados:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('carteira.leads.atualizar', $lead) }}"
          x-data="{ situacao: '{{ old('situacao', $lead->situacao->value) }}' }">
        @csrf
        @method('PUT')

        {{-- Andamento primeiro: é o que ele mexe todo dia. A ficha cadastral
             abaixo só se toca quando a conversa avança. --}}
        <div class="cartao mb-6 p-6">
            <h2 class="mb-4 font-medium text-gray-800 dark:text-white/90">Andamento</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="situacao" class="rotulo-campo">Em que pé está</label>
                    <select id="situacao" name="situacao" class="campo" x-model="situacao" required>
                        @foreach (SituacaoLead::doVendedor() as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    @error('situacao') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                {{-- A data só aparece quando faz sentido: campo de agendamento
                     visível em lead recusado é convite a preencher errado. --}}
                <div x-show="situacao === '{{ SituacaoLead::Agendado->value }}'" x-cloak>
                    <label for="agendado_para" class="rotulo-campo">Quando</label>
                    <input id="agendado_para" name="agendado_para" type="datetime-local" class="campo"
                           value="{{ old('agendado_para', $lead->agendado_para?->format('Y-m-d\TH:i')) }}">
                    <span class="ajuda-campo">Dia e hora da reunião. É por essa data que a sua lista se ordena.</span>
                    @error('agendado_para') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="observacao" class="rotulo-campo">Como foi</label>
                    <textarea id="observacao" name="observacao" rows="4" class="campo"
                              placeholder="Com quem você falou, o que ficou combinado, o que travou.">{{ old('observacao', $lead->observacao) }}</textarea>
                    @error('observacao') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="cartao p-6">
            <h2 class="mb-1 font-medium text-gray-800 dark:text-white/90">Ficha</h2>
            <p class="ajuda-campo mb-4">
                São os mesmos campos do cadastro de cliente. O que você preencher aqui vai
                preenchido na conversão, e ninguém pergunta de novo.
            </p>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nome" class="rotulo-campo">Razão social</label>
                    <input id="nome" name="nome" type="text" class="campo" required
                           value="{{ old('nome', $lead->nome) }}">
                    @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cnpj" class="rotulo-campo">CNPJ</label>
                    <input id="cnpj" name="cnpj" type="text" class="campo"
                           value="{{ old('cnpj', Documento::formatarCnpj($lead->cnpj)) }}"
                           placeholder="00.000.000/0000-00">
                    @error('cnpj') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="rotulo-campo">E-mail</label>
                    <input id="email" name="email" type="text" class="campo"
                           value="{{ old('email', $lead->email) }}">
                    <span class="ajuda-campo">Vira o acesso da empresa à plataforma.</span>
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="telefone" class="rotulo-campo">Telefone</label>
                    <input id="telefone" name="telefone" type="text" class="campo"
                           value="{{ old('telefone', $lead->telefone) }}">
                    @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="responsavel_nome" class="rotulo-campo">Quem decide</label>
                    <input id="responsavel_nome" name="responsavel_nome" type="text" class="campo"
                           value="{{ old('responsavel_nome', $lead->responsavel_nome) }}">
                    @error('responsavel_nome') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="responsavel_cpf" class="rotulo-campo">CPF de quem decide</label>
                    <input id="responsavel_cpf" name="responsavel_cpf" type="text" class="campo"
                           value="{{ old('responsavel_cpf', $lead->responsavel_cpf) }}">
                    @error('responsavel_cpf') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cep" class="rotulo-campo">CEP</label>
                    <input id="cep" name="cep" type="text" class="campo"
                           value="{{ old('cep', $lead->cep) }}">
                    @error('cep') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="logradouro" class="rotulo-campo">Logradouro</label>
                    <input id="logradouro" name="logradouro" type="text" class="campo"
                           value="{{ old('logradouro', $lead->logradouro) }}">
                    @error('logradouro') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="numero" class="rotulo-campo">Número</label>
                    <input id="numero" name="numero" type="text" class="campo"
                           value="{{ old('numero', $lead->numero) }}">
                    @error('numero') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="complemento" class="rotulo-campo">Complemento</label>
                    <input id="complemento" name="complemento" type="text" class="campo"
                           value="{{ old('complemento', $lead->complemento) }}">
                    @error('complemento') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="bairro" class="rotulo-campo">Bairro</label>
                    <input id="bairro" name="bairro" type="text" class="campo"
                           value="{{ old('bairro', $lead->bairro) }}">
                    @error('bairro') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cidade" class="rotulo-campo">Cidade</label>
                    <input id="cidade" name="cidade" type="text" class="campo"
                           value="{{ old('cidade', $lead->cidade) }}">
                    @error('cidade') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="uf" class="rotulo-campo">UF</label>
                    <input id="uf" name="uf" type="text" class="campo" maxlength="2"
                           value="{{ old('uf', $lead->uf) }}">
                    @error('uf') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-avalia.botao type="submit">Salvar</x-avalia.botao>
                <x-avalia.botao variante="secundario" :href="route('carteira.leads')">Cancelar</x-avalia.botao>
            </div>
        </div>
    </form>
@endsection
