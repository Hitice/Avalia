@extends('layouts.app', ['title' => 'Sócios'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Sócios</h1>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <label for="competencia" class="sr-only">Competência</label>
            <select id="competencia" name="competencia" class="campo w-auto py-2" onchange="this.form.submit()">
                @foreach ($competencias as $mes)
                    <option value="{{ $mes }}" @selected($mes === $competencia)>{{ $mes }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Caixa" :valor="Dinheiro::brl($caixa)"
                                   tom="text-brand-600 dark:text-brand-400" />
        <x-avalia.cartao-indicador rotulo="Receita no mês" :valor="Dinheiro::brl($receita)" />
        <x-avalia.cartao-indicador rotulo="Despesa no mês" :valor="Dinheiro::brl($despesa)" />
        <x-avalia.cartao-indicador rotulo="Resultado do mês" :valor="Dinheiro::brl($receita - $despesa)"
                                   ajuda="Só receita e despesa entram." />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">
                    {{ $socios->isEmpty() ? 'Cadastrar sócio' : 'Por sócio' }}
                </h2>
                @if ($socios->isEmpty())
                    <p class="ajuda-campo mt-1">
                        O caixa precisa saber de quem é cada parte. Comece por aqui.
                    </p>
                @endif
            </div>

            {{-- Tabela vazia acima do formulario escondia o cadastro: quem abria
                 a tela pela primeira vez via tres colunas sem linha nenhuma e
                 nao achava por onde comecar. Sem socio, a tabela nao aparece. --}}
            @if ($socios->isNotEmpty())
            <table class="tabela">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="tabela-th text-left">Sócio</th>
                    <th scope="col" class="tabela-th text-right">Aportou</th>
                    <th scope="col" class="tabela-th text-right">A devolver</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($porSocio as $socio)
                        <tr>
                            <td class="tabela-td text-gray-800 dark:text-white/90">{{ $socio['nome'] }}</td>
                            <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($socio['aportou']) }}
                            </td>
                            <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($socio['a_devolver']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            {{-- A soma das participacoes e conferida aqui, e nao no banco:
                 trava no cadastro impediria gravar o primeiro socio, que
                 sozinho nunca fecha 100% enquanto o segundo nao entra. --}}
            @if ($socios->isNotEmpty() && $participacaoTotal !== 10000)
                <p class="aviso aviso-alerta m-5">
                    As participações somam {{ number_format($participacaoTotal / 100, 2, ',', '.') }}%
                    em vez de 100%.
                </p>
            @endif

            <form method="POST" action="{{ route('socios.criar') }}"
                  @class(['grid gap-4 p-5', 'border-t border-gray-100 dark:border-gray-800' => $socios->isNotEmpty()])>
                @csrf
                @if ($socios->isNotEmpty())
                    <h3 class="rotulo-grupo">Cadastrar sócio</h3>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[10rem] flex-1">
                        <label for="socio-nome" class="rotulo-campo">Nome</label>
                        <input id="socio-nome" name="nome" type="text" maxlength="120" required class="campo"
                               value="{{ old('nome') }}">
                        @error('nome')<p class="erro-campo">{{ $message }}</p>@enderror
                    </div>

                    <div class="w-28">
                        <label for="participacao" class="rotulo-campo">Participação</label>
                        <input id="participacao" name="participacao" type="number" step="0.01" min="0" max="100"
                               class="campo" value="{{ old('participacao') }}" placeholder="50">
                    </div>
                </div>

                <div>
                    <label for="socio-staff" class="rotulo-campo">Conta de acesso</label>
                    <select id="socio-staff" name="staff_id" class="campo">
                        <option value="">Nenhuma</option>
                        @foreach ($equipe as $pessoa)
                            <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">Sócio que não opera o sistema também tem quota.</span>
                </div>

                <div>
                    <x-avalia.botao variante="secundario" tamanho="sm">Cadastrar</x-avalia.botao>
                </div>
            </form>
        </div>

        {{-- A tela pergunta a NATUREZA, nunca a conta. Escolher conta e onde o
             erro mora, e a traducao vive em RegistrarLancamento. --}}
        <form method="POST" action="{{ route('socios.registrar') }}" class="cartao grid gap-5 p-6"
              x-data="{ natureza: '{{ old('natureza', 'despesa') }}' }">
            @csrf
            <h2 class="rotulo-grupo">Novo lançamento</h2>

            @if ($socios->isEmpty())
                {{-- Fora do `x-show` de proposito: se o Alpine nao subir, o
                     campo de socio nunca aparece e a pessoa fica sem saber por
                     que o lancamento nao passa. Este aviso nao depende dele. --}}
                <p class="aviso aviso-alerta">
                    Cadastre um sócio antes de lançar aporte, empréstimo, reembolso, retirada ou
                    distribuição. O formulário está no cartão ao lado.
                </p>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="natureza" class="rotulo-campo">Natureza</label>
                    <select id="natureza" name="natureza" class="campo" x-model="natureza" required>
                        @foreach ($naturezas as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="valor" class="rotulo-campo">Valor</label>
                    <input id="valor" name="valor" type="text" inputmode="decimal" required class="campo"
                           value="{{ old('valor') }}" placeholder="1.234,56">
                    @error('valor')<p class="erro-campo">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="descricao" class="rotulo-campo">Descrição</label>
                <input id="descricao" name="descricao" type="text" maxlength="200" required class="campo"
                       value="{{ old('descricao') }}" placeholder="Hospedagem Hostinger, setembro">
                @error('descricao')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="ocorrido_em" class="rotulo-campo">Quando</label>
                    <input id="ocorrido_em" name="ocorrido_em" type="date" required class="campo"
                           value="{{ old('ocorrido_em', now()->toDateString()) }}">
                    <span class="ajuda-campo">A competência sai desta data.</span>
                </div>

                <div x-show="['aporte','emprestimo','despesa_do_socio','reembolso','retirada','distribuicao'].includes(natureza)" x-cloak>
                    <label for="socio_id" class="rotulo-campo">Sócio</label>

                    @if ($socios->isEmpty())
                        {{-- Select vazio com uma opcao "Escolha" que nao escolhe
                             nada e um campo quebrado sem dizer que esta. --}}
                        <p class="campo text-gray-500 dark:text-gray-400">Nenhum sócio cadastrado</p>
                    @else
                        <select id="socio_id" name="socio_id" class="campo">
                            <option value="">Escolha</option>
                            @foreach ($socios as $pessoa)
                                <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                            @endforeach
                        </select>
                    @endif

                    @error('socio_id')<p class="erro-campo">{{ $message }}</p>@enderror
                </div>

                <div x-show="natureza === 'transferencia'" x-cloak>
                    <label for="destino_id" class="rotulo-campo">Conta de destino</label>
                    <select id="destino_id" name="destino_id" class="campo">
                        <option value="">Escolha</option>
                        @foreach ($contas->where('grupo', 'ativo') as $conta)
                            <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-avalia.botao>Registrar</x-avalia.botao>
            </div>
        </form>
    </div>

    <div class="mt-6 cartao overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Extrato de {{ $competencia }}</h2>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela min-w-[40rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="tabela-th text-left">Quando</th>
                    <th scope="col" class="tabela-th text-left">Natureza</th>
                    <th scope="col" class="tabela-th text-left">Descrição</th>
                    <th scope="col" class="tabela-th text-right">Valor</th>
                    <th scope="col" class="tabela-th text-right">Estornar</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($lancamentos as $lancamento)
                        <tr @class(['opacity-60' => $lancamento->estorna_id !== null])>
                            <td class="tabela-td tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $lancamento->ocorrido_em->format('d/m') }}
                            </td>
                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                {{ $lancamento->natureza->rotulo() }}
                            </td>
                            <td class="tabela-td text-gray-800 dark:text-white/90">
                                {{ $lancamento->descricao }}
                                <span class="ajuda-campo">{{ $lancamento->staff?->nome }}</span>
                            </td>
                            <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($lancamento->valorCents()) }}
                            </td>
                            <td class="tabela-td text-right">
                                @if ($lancamento->estorna_id === null && ! $lancamento->estornado())
                                    <form method="POST" action="{{ route('socios.estornar', $lancamento) }}"
                                          onsubmit="this.motivo.value = prompt('Motivo do estorno?') || ''; return this.motivo.value !== '';">
                                        @csrf
                                        <input type="hidden" name="motivo" value="">
                                        <x-avalia.botao variante="secundario" tamanho="sm">Estornar</x-avalia.botao>
                                    </form>
                                @elseif ($lancamento->estorna_id !== null)
                                    <span class="etiqueta etiqueta-neutra">estorno</span>
                                @else
                                    <span class="etiqueta etiqueta-neutra">estornado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabela-vazia">Nenhum lançamento nesta competência.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
