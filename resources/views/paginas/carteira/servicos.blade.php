@extends('layouts.app', ['title' => 'Serviços'])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Minha carteira</h1>
    </div>

    @include('paginas.carteira.abas')

    @if ($planos->isEmpty())
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">Nenhum plano ativo no catálogo vigente.</p>
        </div>
    @else
        {{-- Todas as faixas lado a lado: a comparacao que o vendedor faz ao
             telefone, sem trocar de tela. O numero e o nome de atendimento do
             servico: "consulta 7" se dita e se anota. --}}
        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Tabela de preços por plano</h2>
                <p class="ajuda-campo mt-1">Preço por consulta que a empresa contratante paga em cada plano.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="tabela min-w-[44rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Nº</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                        @foreach ($planos as $plano)
                            <th scope="col" class="px-4 py-3 text-right font-medium">
                                {{ $plano->nome }}
                                <span class="block text-xs font-normal normal-case text-gray-400 dark:text-gray-500">
                                    mínimo {{ $plano->consumo_minimo }}
                                </span>
                            </th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($servicos as $servico)
                            <tr>
                                <td class="px-4 py-4 text-right font-medium tabular-nums text-gray-500 dark:text-gray-400">{{ $servico->numero }}</td>
                                <td class="px-5 py-4 text-left">
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $servico->nome }}</span>
                                    @if ($servico->descricao)
                                        <span class="mt-0.5 block max-w-md text-xs text-gray-500 dark:text-gray-400">{{ $servico->descricao }}</span>
                                    @endif
                                </td>
                                @foreach ($planos as $plano)
                                    @php $preco = $precos->get($plano->catalogo_id.':'.$plano->faixaDePrecoCents().':'.$servico->id); @endphp
                                    <td class="px-4 py-4 text-right tabular-nums {{ $preco ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-600' }}">
                                        {{ $preco ? Dinheiro::brl($preco->preco_cents) : 'não incluso' }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 2 + $planos->count() }}" class="tabela-vazia">Nenhum serviço disponível para venda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($planos as $plano)
                <div class="cartao p-4">
                    <span class="rotulo-grupo block">{{ $plano->nome }}</span>
                    <span class="mt-1 block text-sm text-gray-700 dark:text-gray-300">
                        Mensalidade {{ $plano->mensalidade }} · mínimo {{ $plano->consumo_minimo }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
@endsection
