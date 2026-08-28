@extends('layouts.app', ['title' => $empresa->exists ? $empresa->razao_social : 'Novo cliente'])

@php
    use App\Support\Dinheiro;
    use App\Support\Documento;

    // Carteira e situacao sao decisoes da administracao. O vendedor nao ve os
    // campos, e o controller ignora os dois mesmo que venham forjados no POST.
    $ehAdmin = auth('staff')->user()?->ehAdmin();
@endphp

@section('content')
    <a href="{{ $ehAdmin ? ($empresa->exists ? route('empresas.ficha', $empresa) : route('empresas.index')) : route('carteira') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        {{ $ehAdmin ? ($empresa->exists ? $empresa->razao_social : 'Clientes') : 'Minha carteira' }}
    </a>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $empresa->exists ? 'Editar cadastro' : ($ehAdmin ? 'Novo cliente' : 'Nova empresa') }}
        </h1>

        @if ($empresa->exists)
            {{-- Fora do formulario principal: form dentro de form nao existe
                 em HTML. Nao mexe na senha atual, so manda o link novo. --}}
            <form method="POST" action="{{ route('empresas.convite', $empresa) }}">
                @csrf
                <x-avalia.botao variante="secundario" tamanho="sm">
                    Enviar redefinição de senha
                </x-avalia.botao>
            </form>
        @endif
    </div>

    @include('paginas.catalogo.avisos')

    {{-- Conversao de lead: o formulario e o mesmo, com os campos copiados da
         ficha. O aviso existe porque salvar aqui tambem fecha o lead. --}}
    @isset($lead)
        <div class="aviso aviso-ok mb-6">
            Cadastro aberto a partir do lead <strong>{{ $lead->nome }}</strong>. Os campos vieram da
            ficha; complete o que faltar. Ao salvar, o lead sai da prospecção.
        </div>
    @endisset

    @if ($errors->any())
        {{-- O formulario e longo: sem o resumo aqui em cima, o erro la embaixo
             fica fora da dobra e o clique parece nao ter feito nada. --}}
        <div class="aviso aviso-erro mb-6">
            <p class="font-medium">Confira os campos destacados:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="cartao p-6">
        <form method="POST"
              action="{{ $empresa->exists ? route('empresas.atualizar', $empresa) : route('empresas.salvar') }}">
            @csrf
            @if ($empresa->exists)
                @method('PUT')
            @endif

            @isset($lead)
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
            @endisset

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="razao_social" class="rotulo-campo">Razão social</label>
                    <input id="razao_social" name="razao_social" type="text" class="campo" required
                           value="{{ old('razao_social', $empresa->razao_social) }}">
                    @error('razao_social') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="cnpj" class="rotulo-campo">CNPJ</label>
                    <input id="cnpj" name="cnpj" type="text" class="campo" required
                           value="{{ old('cnpj', Documento::formatarCnpj($empresa->cnpj)) }}"
                           placeholder="12.345.678/0001-95">
                    <span class="ajuda-campo">Aceita letra: o CNPJ alfanumérico vale desde julho de 2026.</span>
                    @error('cnpj') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div @class(['hidden' => ! $ehAdmin])>
                    <label for="situacao" class="rotulo-campo">Situação</label>
                    <select id="situacao" name="situacao" class="campo" required>
                        @foreach (App\Support\Rotulos::situacoesDaEmpresa() as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('situacao', $empresa->situacao) === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Só empresa ativa consulta. Suspensa e bloqueada ainda entram para ver a fatura.</span>
                </div>

                <div>
                    <label for="email" class="rotulo-campo">E-mail de acesso</label>
                    <input id="email" name="email" type="email" class="campo" required
                           value="{{ old('email', $empresa->email) }}">
                    @error('email') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="telefone" class="rotulo-campo">Telefone</label>
                    <input id="telefone" name="telefone" type="text" class="campo" value="{{ old('telefone', $empresa->telefone) }}" placeholder="(00) 00000-0000">
                    @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="responsavel_nome" class="rotulo-campo">Responsável pelo contrato</label>
                    <input id="responsavel_nome" name="responsavel_nome" type="text" class="campo" value="{{ old('responsavel_nome', $empresa->responsavel_nome) }}">
                    @error('responsavel_nome') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="responsavel_cpf" class="rotulo-campo">CPF do responsável</label>
                    <input id="responsavel_cpf" name="responsavel_cpf" type="text" class="campo" value="{{ old('responsavel_cpf', $empresa->responsavel_cpf) }}">
                    @error('responsavel_cpf') <span class="erro-campo">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="plano_id" class="rotulo-campo">Plano contratado</label>
                    <select id="plano_id" name="plano_id" class="campo">
                        <option value="">Sem plano</option>
                        @foreach ($planos as $plano)
                            <option value="{{ $plano->id }}" @selected(old('plano_id', $empresa->plano_id) == $plano->id)>
                                {{ $plano->nome }} · {{ Dinheiro::faixa($plano->consumo_minimo_cents) }}
                            </option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Define a faixa de preços aplicada às consultas desta empresa.</span>
                </div>

                <div @class(['hidden' => ! $ehAdmin])>
                    <label for="vendedor_id" class="rotulo-campo">Vendedor</label>
                    <select id="vendedor_id" name="vendedor_id" class="campo">
                        <option value="">Sem vendedor responsável</option>
                        @foreach ($vendedores as $vendedor)
                            <option value="{{ $vendedor->id }}" @selected(old('vendedor_id', $empresa->vendedor_id) == $vendedor->id)>
                                {{ $vendedor->nome }}
                            </option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Quem recebe a comissão das faturas desta empresa.</span>
                </div>

                @if ($ehAdmin)
                    <div class="sm:col-span-2 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <h2 class="font-medium text-gray-800 dark:text-white/90">Condições comerciais</h2>
                    </div>
                    {{-- Cada tipo mostra so as datas que lhe pertencem: 12 e 24
                         meses calculam o fim sozinhos a partir do inicio;
                         carencia pede a data dela; sem vigencia nao pede nada.
                         O fim digitavel ao lado de um prazo fechado era a
                         ambiguidade, e o servidor resolve as datas de novo ao
                         gravar, entao esconder aqui nao e so cosmetico. --}}
                    <div x-data="{ vig: '{{ old('vigencia_tipo', $empresa->vigencia_tipo ?? '') }}' }"
                         class="contents">
                        <div>
                            <label for="vigencia_tipo" class="rotulo-campo">Vigência</label>
                            <select id="vigencia_tipo" name="vigencia_tipo" class="campo" x-model="vig">
                                <option value="">Não definida</option>
                                <option value="sem_vigencia">Sem vigência</option>
                                <option value="12_meses">12 meses</option>
                                <option value="24_meses">24 meses</option>
                                <option value="carencia">Carência especial</option>
                            </select>
                        </div>
                        <div x-show="vig !== '' && vig !== 'sem_vigencia'">
                            <label for="contrato_inicio" class="rotulo-campo">Início do contrato</label>
                            <input id="contrato_inicio" name="contrato_inicio" type="date" class="campo" value="{{ old('contrato_inicio', optional($empresa->contrato_inicio)->format('Y-m-d')) }}">
                            <span class="ajuda-campo" x-show="vig === '12_meses' || vig === '24_meses'">
                                O fim da vigência é calculado a partir desta data.
                            </span>
                        </div>
                        <div x-cloak x-show="vig === 'carencia'">
                            <label for="carencia_ate" class="rotulo-campo">Carência até</label>
                            <input id="carencia_ate" name="carencia_ate" type="date" class="campo" value="{{ old('carencia_ate', optional($empresa->carencia_ate)->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div>
                        <label for="adesao_valor" class="rotulo-campo">Taxa de adesão</label>
                        <input id="adesao_valor" name="adesao_valor" type="text" inputmode="decimal" class="campo" value="{{ old('adesao_valor', $empresa->adesao ? Dinheiro::numero($empresa->adesao->valor_cents) : '') }}" placeholder="0,00">
                        <span class="ajuda-campo">O valor liquidado é dividido igualmente entre a Avalia e o vendedor.</span>
                    </div>
                    <div>
                        <label for="adesao_parcelas" class="rotulo-campo">Parcelas da adesão</label>
                        <input id="adesao_parcelas" name="adesao_parcelas" type="number" min="1" max="120" class="campo" value="{{ old('adesao_parcelas', $empresa->adesao->parcelas ?? 1) }}">
                    </div>
                @endif

                <div class="sm:col-span-2 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <h2 class="font-medium text-gray-800 dark:text-white/90">Endereço</h2>
                </div>
                <div>
                    <label for="cep" class="rotulo-campo">CEP</label>
                    <input id="cep" name="cep" type="text" class="campo" value="{{ old('cep', $empresa->cep) }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="logradouro" class="rotulo-campo">Logradouro</label>
                    <input id="logradouro" name="logradouro" type="text" class="campo" value="{{ old('logradouro', $empresa->logradouro) }}">
                </div>
                <div><label for="numero" class="rotulo-campo">Número</label><input id="numero" name="numero" type="text" class="campo" value="{{ old('numero', $empresa->numero) }}"></div>
                <div><label for="complemento" class="rotulo-campo">Complemento</label><input id="complemento" name="complemento" type="text" class="campo" value="{{ old('complemento', $empresa->complemento) }}"></div>
                <div><label for="bairro" class="rotulo-campo">Bairro</label><input id="bairro" name="bairro" type="text" class="campo" value="{{ old('bairro', $empresa->bairro) }}"></div>
                <div><label for="cidade" class="rotulo-campo">Cidade</label><input id="cidade" name="cidade" type="text" class="campo" value="{{ old('cidade', $empresa->cidade) }}"></div>
                <div><label for="uf" class="rotulo-campo">UF</label><input id="uf" name="uf" type="text" maxlength="2" class="campo" value="{{ old('uf', $empresa->uf) }}"></div>
            </div>

            <div class="mt-8 flex gap-3">
                <x-avalia.botao>{{ $empresa->exists ? 'Salvar' : 'Cadastrar' }}</x-avalia.botao>
                <x-avalia.botao variante="secundario"
                                :href="$ehAdmin ? ($empresa->exists ? route('empresas.ficha', $empresa) : route('empresas.index')) : route('carteira')">
                    Cancelar
                </x-avalia.botao>
            </div>
        </form>
    </div>

    @if ($empresa->exists && $ehAdmin)
        {{-- Cada pessoa que consulta tem conta propria: e a resposta de LGPD
             para "quem consultou este documento". A senha nasce aleatoria e o
             convite por e-mail leva o link para a pessoa definir a dela. --}}
        <div class="cartao mt-6 p-6 lg:p-8">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Operadores</h2>
            <p class="ajuda-campo mt-1">
                Pessoas da empresa que fazem consultas, cada uma com o próprio acesso e histórico.
                Cada operador aceita os termos no primeiro acesso.
            </p>

            @if ($empresa->operadores->isNotEmpty())
                <div class="mt-5 overflow-x-auto">
                    <table class="tabela min-w-[36rem]">
                        <thead class="tabela-cabecalho"><tr>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Nome</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">E-mail</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium"><span class="sr-only">Ações</span></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($empresa->operadores as $operador)
                                <tr>
                                    <td class="px-5 py-4 text-left font-medium text-gray-800 dark:text-white/90">{{ $operador->nome }}</td>
                                    <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">{{ $operador->email }}</td>
                                    <td class="px-5 py-4 text-left">
                                        <span class="etiqueta {{ $operador->ativo ? 'etiqueta-sucesso' : 'etiqueta-neutra' }}">
                                            {{ $operador->ativo ? 'Ativo' : 'Desativado' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <form method="POST" action="{{ route('empresas.operadores.convite', [$empresa, $operador]) }}" class="inline">
                                            @csrf
                                            <x-avalia.botao variante="secundario" tamanho="sm">Reenviar convite</x-avalia.botao>
                                        </form>
                                        <form method="POST" action="{{ route('empresas.operadores.alternar', [$empresa, $operador]) }}" class="inline">
                                            @csrf
                                            <x-avalia.botao variante="secundario" tamanho="sm">{{ $operador->ativo ? 'Desativar' : 'Reativar' }}</x-avalia.botao>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('empresas.operadores.salvar', $empresa) }}"
                  class="mt-5 flex flex-wrap items-end gap-4 border-t border-gray-100 pt-5 dark:border-gray-800">
                @csrf
                <div class="min-w-56 grow sm:max-w-xs">
                    <label for="operador-nome" class="rotulo-campo">Nome</label>
                    <input id="operador-nome" name="nome" type="text" class="campo" required minlength="5" maxlength="150">
                </div>
                <div class="min-w-56 grow sm:max-w-xs">
                    <label for="operador-email" class="rotulo-campo">E-mail</label>
                    <input id="operador-email" name="email" type="email" class="campo" required maxlength="150">
                </div>
                <x-avalia.botao tamanho="sm">Criar operador</x-avalia.botao>
            </form>
            @error('nome') <span class="erro-campo mt-2 block">{{ $message }}</span> @enderror
            @error('email') <span class="erro-campo mt-2 block">{{ $message }}</span> @enderror
        </div>
    @endif
@endsection
