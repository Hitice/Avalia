@extends('layouts.app', ['title' => 'Meu estoque'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Meu estoque</h1>
        <p class="rotulo-grupo mt-1">As placas que estão na sua mão, ainda sem venda</p>
    </div>

    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-avalia.cartao-indicador rotulo="Na minha mão" :valor="$minhas->count()"
                                   tom="text-brand-600 dark:text-brand-400" />

        <x-avalia.cartao-indicador rotulo="Meu link de cadastro de cliente"
                                   :valor="auth('staff')->user()->codigo_indicacao"
                                   ajuda="Cadastra o cliente no seu nome, mesmo sem venda" />

        @if ($ehAdmin)
            <x-avalia.cartao-indicador rotulo="Livres no estoque da casa" :valor="$noBolo" />
        @endif
    </div>

    <div class="cartao mb-6 p-5">
        <label for="meu-link" class="rotulo-campo">Link para mandar ao cliente</label>
        <input id="meu-link" type="text" class="campo" readonly onfocus="this.select()"
               value="{{ auth('staff')->user()->linkDeCadastro() }}">
        <span class="ajuda-campo">
            Quem preencher entra na base como seu cliente, com venda ou sem.
        </span>
    </div>

    @if ($ehAdmin)
        <div class="cartao mb-6 p-5">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Entregar placas</h2>

            <form method="POST" action="{{ route('salles.estoque.entregar') }}"
                  class="flex flex-wrap items-end gap-3">
                @csrf

                <div class="min-w-[14rem]">
                    <label for="vendedor_id" class="rotulo-campo">Para quem</label>
                    <select id="vendedor_id" name="vendedor_id" class="campo" required>
                        @foreach ($equipe as $pessoa)
                            <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="quantas" class="rotulo-campo">Quantas</label>
                    <input id="quantas" name="quantas" type="number" min="1" max="500" value="20"
                           class="campo w-28" required>
                </div>

                <x-avalia.botao>Entregar</x-avalia.botao>
            </form>

            <p class="ajuda-campo mt-3">
                Saem as de menor número primeiro, para o lote chegar em ordem à bancada.
            </p>
        </div>

        <div class="cartao mb-6 overflow-hidden">
            <h2 class="p-5 pb-0 text-lg font-semibold text-gray-800 dark:text-white/90">Estoque da equipe</h2>

            <div class="tabela-rolagem mt-4">
                <table class="tabela">
                    <thead class="tabela-cabecalho">
                        <tr>
                            <th class="tabela-th text-left">Vendedor</th>
                            <th class="tabela-th text-right">Na mão</th>
                            <th class="tabela-th text-right">Já vendeu</th>
                            <th class="tabela-th text-right">Devolver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($porVendedor as $pessoa)
                            <tr>
                                <td class="tabela-td">{{ $pessoa->nome }}</td>
                                <td class="tabela-td text-right tabular-nums">{{ $pessoa->em_maos }}</td>
                                <td class="tabela-td text-right tabular-nums">{{ $pessoa->vendidas }}</td>
                                <td class="tabela-td text-right">
                                    @if ($pessoa->em_maos > 0)
                                        <form method="POST" action="{{ route('salles.estoque.devolver', $pessoa) }}">
                                            @csrf
                                            <x-avalia.botao variante="secundario" class="botao-sm">
                                                Devolver {{ $pessoa->em_maos }}
                                            </x-avalia.botao>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="tabela-vazia">
                                    Ninguém pegou placa ainda. Use o formulário acima.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <h2 class="p-5 pb-0 text-lg font-semibold text-gray-800 dark:text-white/90">
            As minhas, uma a uma
        </h2>

        <div class="tabela-rolagem mt-4">
            <table class="tabela">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="tabela-th text-left">Número</th>
                        <th class="tabela-th text-left">Código</th>
                        <th class="tabela-th text-left">Desde</th>
                        <th class="tabela-th text-right">Apontar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($minhas as $placa)
                        <tr>
                            <td class="tabela-td tabular-nums">{{ $placa->sequencia ?? '—' }}</td>
                            <td class="tabela-td font-mono">{{ $placa->codigo }}</td>
                            <td class="tabela-td">{{ $placa->consignada_em?->format('d/m/Y') ?? '—' }}</td>
                            <td class="tabela-td text-right">
                                <a href="{{ route('etiquetas.ficha', $placa) }}"
                                   class="botao botao-secundario botao-sm">Apontar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tabela-vazia">
                                Você não tem placa em mão. Fale com a administração.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
