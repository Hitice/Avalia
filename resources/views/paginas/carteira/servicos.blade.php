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
        {{-- O preco muda por faixa, entao a faixa e a primeira escolha da tela e
             nao uma coluna a mais: o vendedor abre no plano que esta propondo. --}}
        <x-avalia.segmentado
            class="mb-6"
            rotulo="Plano"
            :atual="$plano?->id"
            :itens="$planos->mapWithKeys(fn ($p) => [
                $p->id => ['rotulo' => $p->nome, 'url' => route('carteira.servicos', ['plano' => $p->id])],
            ])->all()" />

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="cartao p-5">
                <span class="rotulo-grupo block">Mensalidade</span>
                <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ $plano->mensalidade }}</span>
            </div>
            <div class="cartao p-5">
                <span class="rotulo-grupo block">Consumo mínimo</span>
                <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ $plano->consumo_minimo }}</span>
                <span class="ajuda-campo">Cobrado mesmo sem uso.</span>
            </div>
            <div class="cartao p-5">
                <span class="rotulo-grupo block">Serviços liberados</span>
                <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ $servicos->count() }}</span>
            </div>
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">O que você pode vender neste plano</h2>
                <p class="ajuda-campo mt-1">Preço por consulta que a empresa contratante paga.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="tabela min-w-[32rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Código</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Preço ao cliente</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($servicos as $servico)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $servico->nome }}</td>
                                <td class="px-5 py-4 text-left text-gray-500 dark:text-gray-400">{{ $servico->codigo }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl((int) ($precos[$servico->id] ?? 0)) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tabela-vazia">Nenhum serviço precificado nesta faixa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
