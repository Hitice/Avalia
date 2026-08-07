@extends('layouts.app', ['title' => 'Simulador'])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Simulador</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Quanto sai o mês para o seu plano, antes de consultar. Nada aqui é cobrado.
            </p>
        </div>
        <x-avalia.ajuda assunto="Simulador">Falar com a Avalia</x-avalia.ajuda>
    </div>

    @if (! $plano)
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Sua empresa ainda não tem plano contratado. Fale com a sua equipe comercial.
            </p>
        </div>
    @else
        <form method="GET" action="{{ route('empresa.simulador') }}" class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="cartao overflow-hidden">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h2 class="font-medium text-gray-800 dark:text-white/90">Consultas no mês</h2>
                    <p class="ajuda-campo mt-1">Digite quantas consultas de cada serviço você estima fazer.</p>
                </div>
                <div class="tabela-rolagem">
                    <table class="tabela min-w-[36rem]">
                        <thead class="tabela-cabecalho tabela-cabecalho-fixo"><tr>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Nº</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Preço</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Incluídas no plano</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Quantidade</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($linhas as $linha)
                                <tr>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $linha['servico']->numero }}</td>
                                    <td class="px-5 py-3 text-left text-gray-800 dark:text-white/90">{{ $linha['servico']->nome }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ Dinheiro::brl($linha['preco_cents']) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $linha['franquia'] }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <input type="number" min="0" max="1000000" name="q[{{ $linha['servico']->id }}]"
                                               value="{{ $linha['quantidade'] ?: '' }}" placeholder="0"
                                               class="campo-celula">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    <x-avalia.botao>Simular</x-avalia.botao>
                </div>
            </div>

            {{-- O resumo com a mesma conta do fechamento: franquia nao soma,
                 excedente compara com o minimo, e o total e o que a fatura
                 traria. A tela e a fatura tem que fechar no centavo. --}}
            <div class="cartao h-fit p-6">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Fatura estimada</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Mensalidade</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($plano->mensalidade_cents) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Consultas além do incluído</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($excedente) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Consumo mínimo do plano</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($minimo) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Consumo considerado</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($faturado) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                        <dt class="font-medium text-gray-800 dark:text-white/90">Total do mês</dt>
                        <dd class="tabular-nums text-lg font-semibold text-gray-800 dark:text-white/90">{{ Dinheiro::brl($total) }}</dd>
                    </div>
                </dl>

                @if ($excedente < $minimo)
                    <p class="ajuda-campo mt-4">
                        Neste cenário o consumo fica abaixo do mínimo: o mês sai pelo piso contratado.
                    </p>
                @endif
            </div>
        </form>
    @endif
@endsection
