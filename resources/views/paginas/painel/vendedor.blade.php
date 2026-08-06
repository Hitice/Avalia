@extends('layouts.app', ['title' => 'Visão geral'])

@php
    use App\Support\Alertas;
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Olá, {{ $staff->nome }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Resumo da sua carteira · {{ $competencia }}</p>
    </div>

    {{-- Nenhum numero da operacao aqui: "a receber" e "em atraso" sao dinheiro
         da Avalia, e apareciam nesta tela sem que o vendedor pudesse fazer nada
         com eles. O que ele ve e o que e dele e o que depende dele. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Empresas ativas</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $clientesAtivos }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consultas no período</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $consultas }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Comissão liberada</span>
            <span class="mt-1 block text-2xl font-semibold text-success-600 dark:text-success-500 tabular-nums">{{ Dinheiro::brl($comissaoLiberada) }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Aguardando pagamento</span>
            <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($comissaoAConfirmar) }}</span>
            <span class="ajuda-campo">Libera quando a empresa paga.</span>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- A janela entre o vencimento e a suspensao automatica. Depois dela a
             empresa para de consultar sozinha, e o vendedor descobre pela
             reclamacao. --}}
        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Ligar hoje</h2>
                <p class="ajuda-campo mt-1">Fatura vencida. Passado o prazo, as consultas são suspensas.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[26rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Período</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Prazo</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($aCaminhoDaSuspensao as $linha)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $linha['fatura']->cliente->razao_social }}</td>
                                <td class="px-5 py-4 text-left text-gray-500 dark:text-gray-400">{{ $linha['fatura']->competenciaRotulo() }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span class="etiqueta {{ $linha['dias'] <= 2 ? 'etiqueta-erro' : 'etiqueta-alerta' }}">
                                        {{ $linha['dias'] === 0 ? 'Hoje' : $linha['dias'].($linha['dias'] === 1 ? ' dia' : ' dias') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tabela-vazia">Nenhuma empresa da carteira com fatura vencida.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cliente que para de usar nao reclama: cancela na renovacao. Quem
             nunca consultou vem primeiro, que e o caso mais urgente. --}}
        <div class="cartao overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div>
                    <h2 class="font-medium text-gray-800 dark:text-white/90">Pararam de consultar</h2>
                    <p class="ajuda-campo mt-1">Sem consulta há mais de {{ Alertas::DIAS_SEM_CONSULTAR }} dias.</p>
                </div>
                <a class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" href="{{ route('carteira.consultas') }}">Ver consultas</a>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[26rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Última consulta</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($pararamDeConsultar as $linha)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $linha['cliente']->razao_social }}</td>
                                <td class="px-5 py-4 text-right text-gray-500 dark:text-gray-400">
                                    {{ $linha['ultima']?->format('d/m/Y') ?? 'Nunca consultou' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="tabela-vazia">Todas as empresas da carteira consultaram no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
