@extends('layouts.app', ['title' => 'Contas a pagar'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <x-avalia.cabecalho-pagina titulo="Contas a pagar" subtitulo="Despesas provisionadas e pagas" />

    @include('parciais.avisos')

    <div class="grid gap-6 lg:grid-cols-[1fr_1.6fr]">
        <form method="POST" action="{{ route('erp.contas.salvar') }}" class="cartao grid gap-4 p-6">
            @csrf
            <h2 class="rotulo-grupo">Nova conta</h2>
            <div>
                <label for="descricao" class="rotulo-campo">Descrição</label>
                <input id="descricao" name="descricao" type="text" maxlength="200" required class="campo" value="{{ old('descricao') }}" placeholder="Hospedagem Hostinger, outubro">
                @error('descricao')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="fornecedor" class="rotulo-campo">Fornecedor</label>
                <input id="fornecedor" name="fornecedor" type="text" maxlength="150" class="campo" value="{{ old('fornecedor') }}">
            </div>
            <div>
                <label for="categoria_id" class="rotulo-campo">Categoria</label>
                <select id="categoria_id" name="categoria_id" class="campo" required>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected((string) old('categoria_id') === (string) $categoria->id)>{{ $categoria->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="valor" class="rotulo-campo">Valor</label>
                    <input id="valor" name="valor" type="text" inputmode="decimal" required class="campo" value="{{ old('valor') }}" placeholder="1.234,56">
                    @error('valor')<p class="erro-campo">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="vence_em" class="rotulo-campo">Vence em</label>
                    <input id="vence_em" name="vence_em" type="date" required class="campo" value="{{ old('vence_em', now()->toDateString()) }}">
                </div>
            </div>
            <div><x-avalia.botao>Registrar</x-avalia.botao></div>
        </form>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="titulo-cartao">Em aberto</h2>
            </div>
            <div class="tabela-rolagem">
                <table class="tabela min-w-[36rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="tabela-th text-left">Vence</th>
                        <th scope="col" class="tabela-th text-left">Conta</th>
                        <th scope="col" class="tabela-th text-left">Categoria</th>
                        <th scope="col" class="tabela-th text-right">Valor</th>
                        <th scope="col" class="tabela-th text-right">Pagar</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($abertas as $conta)
                            <tr>
                                <td class="tabela-td tabular-nums {{ $conta->vence_em->isPast() ? 'text-error-600 dark:text-error-500' : 'text-gray-600 dark:text-gray-300' }}">{{ $conta->vence_em->format('d/m') }}</td>
                                <td class="tabela-td text-gray-800 dark:text-white/90">{{ $conta->descricao }}<span class="ajuda-campo">{{ $conta->fornecedor }}</span></td>
                                <td class="tabela-td text-gray-600 dark:text-gray-300">{{ $conta->categoria->nome }}</td>
                                <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($conta->valor_cents) }}</td>
                                <td class="tabela-td text-right">
                                    <form method="POST" action="{{ route('erp.contas.pagar', $conta) }}" onsubmit="return confirm('Marcar como paga?')">
                                        @csrf
                                        <x-avalia.botao variante="secundario" tamanho="sm">Pagar</x-avalia.botao>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="tabela-vazia">Nenhuma conta em aberto.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="titulo-cartao">Pagas</h2>
            </div>
            <table class="tabela">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($pagas as $conta)
                        <tr>
                            <td class="tabela-td tabular-nums text-gray-500 dark:text-gray-400">{{ $conta->pago_em->format('d/m') }}</td>
                            <td class="tabela-td text-gray-600 dark:text-gray-300">{{ $conta->descricao }}</td>
                            <td class="tabela-td text-right tabular-nums text-gray-600 dark:text-gray-300">{{ Dinheiro::brl($conta->valor_cents) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="tabela-vazia">Nenhuma conta paga ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
