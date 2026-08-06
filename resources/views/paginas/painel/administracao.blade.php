@extends('layouts.app', ['title' => 'Visão geral'])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Olá, {{ $staff->nome }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Resumo da operação · {{ $competencia }}</p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Empresas ativas</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $clientesAtivos }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consultas no período</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $consultas }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consumo do período</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($consumoCents) }}</span>
            <span class="ajuda-campo">Ainda não faturado.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Custo do fornecedor</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($custoCents) }}</span>
            <span class="ajuda-campo">Do consumo deste período.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">A receber</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($aReceber) }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Em atraso</span>
            <span class="mt-1 block text-2xl font-semibold text-error-600 dark:text-error-400 tabular-nums">{{ Dinheiro::brl($vencido) }}</span>
            <span class="ajuda-campo">{{ $inadimplentes }} {{ $inadimplentes === 1 ? 'empresa suspensa' : 'empresas suspensas' }}.</span>
        </div>
    </div>

    @if (session('ok'))
        <div class="aviso aviso-sucesso mb-6">{{ session('ok') }}</div>
    @endif

    {{-- Pedidos de contato da pagina publica. Lead esfria em horas, entao a
         fila mora na primeira tela que a administracao abre. --}}
    @if ($interessados->isNotEmpty())
        <div class="cartao mb-6 overflow-hidden border-brand-200 dark:border-brand-500/40">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Pedidos de contato aguardando retorno</h2>
                <p class="ajuda-campo mt-1">Chegaram pela campanha da página pública. Retorno prometido: ainda hoje.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[44rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Quando</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Nome</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Contato</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Funcionários</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium"><span class="sr-only">Ações</span></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($interessados as $interessado)
                            <tr>
                                <td class="px-5 py-4 text-left whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $interessado->created_at->format('d/m H:i') }}</td>
                                <td class="px-5 py-4 text-left font-medium text-gray-800 dark:text-white/90">{{ $interessado->nome }}</td>
                                <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">{{ $interessado->empresa }}</td>
                                <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                    {{ $interessado->telefone }}
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $interessado->email }}</span>
                                </td>
                                <td class="px-5 py-4 text-right text-gray-600 dark:text-gray-300">{{ $interessado->funcionarios }}</td>
                                <td class="px-5 py-4 text-right">
                                    {{-- Atender e tirar da fila: o registro fica para medir conversao. --}}
                                    <form method="POST" action="{{ route('interessados.atendido', $interessado) }}" class="inline">
                                        @csrf
                                        <x-avalia.botao variante="secundario" tamanho="sm">Atendido</x-avalia.botao>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- A janela entre o vencimento e a suspensao automatica: e o unico
             momento em que uma ligacao ainda evita a interrupcao. --}}
        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">A caminho da suspensão</h2>
                <p class="ajuda-campo mt-1">Fatura vencida, consultas ainda liberadas.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[28rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Vendedor</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Valor</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Prazo</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($aCaminhoDaSuspensao as $linha)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">
                                    <a class="hover:text-brand-600 dark:hover:text-brand-400" href="{{ route('empresas.ficha', $linha['fatura']->cliente) }}">
                                        {{ $linha['fatura']->cliente->razao_social }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 text-left text-gray-500 dark:text-gray-400">{{ $linha['fatura']->vendedor?->nome ?? 'Sem vendedor' }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $linha['fatura']->totalRotulo() }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span class="etiqueta {{ $linha['dias'] <= 2 ? 'etiqueta-erro' : 'etiqueta-alerta' }}">
                                        {{ $linha['dias'] === 0 ? 'Hoje' : $linha['dias'].($linha['dias'] === 1 ? ' dia' : ' dias') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabela-vazia">Nenhuma empresa em risco de suspensão.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Comissão liberada por vendedor</h2>
                <p class="ajuda-campo mt-1">Faturas já liquidadas pelas empresas.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[24rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Vendedor</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">A repassar</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($comissaoPorVendedor as $linha)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $linha['nome'] }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ Dinheiro::brl($linha['cents']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="tabela-vazia">Nenhuma comissão liberada até agora.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
