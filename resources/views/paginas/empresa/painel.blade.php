@extends('layouts.app', ['title' => 'Painel'])

@php
    use App\Support\Dinheiro;
    $pagamento = ['liquidado' => 'etiqueta-sucesso', 'vencido' => 'etiqueta-alerta', 'pendente' => 'etiqueta-neutra'];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $empresa->razao_social }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Competência {{ $competencia }}</p>
        </div>
        <x-avalia.ajuda assunto="Painel">Falar com a Avalia</x-avalia.ajuda>
    </div>

    {{-- Conta suspensa entra, mas nao consulta. A tela diz o porque em vez de
         so esconder o botao. Cliente sem explicacao liga para o vendedor. --}}
    @if (! $empresa->podeConsultar())
        <div class="aviso aviso-alerta mb-6">{{ $empresa->motivoSuspensao() }}</div>
    @endif

    {{-- Pendencia de aceite avisa aqui, na entrada, e nao la no fundo da acao:
         e melhor o cliente saber antes do que descobrir com o formulario
         preenchido e a consulta recusada. --}}
    @if ($pendentes->isNotEmpty())
        <div class="aviso aviso-alerta mb-6">
            {{ $pendentes->count() === 1 ? 'Há um documento aguardando seu aceite' : 'Há '.$pendentes->count().' documentos aguardando seu aceite' }}.
            As consultas ficam bloqueadas até o aceite.
            <a class="font-medium underline" href="{{ route('empresa.documentos') }}">Ver documentos</a>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Plano contratado</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ $plano?->nome ?? 'Não definido' }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Mensalidade</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90">{{ Dinheiro::brl($plano?->mensalidade_cents ?? 0) }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consumo do mês</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($consumoCents) }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Faturas em aberto</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">
                {{ Dinheiro::brl((int) $emAberto->sum('total_cents')) }}
            </span>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($plano)
            <div class="cartao overflow-hidden">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h2 class="font-medium text-gray-800 dark:text-white/90">Franquia de consultas</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="tabela min-w-[28rem]">
                        <thead class="tabela-cabecalho"><tr>
                            <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Incluídas</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Utilizadas</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Disponíveis</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($plano->franquias as $franquia)
                                @php $utilizadas = (int) ($uso[$franquia->servico_id] ?? 0); @endphp
                                <tr>
                                    <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $franquia->servico->nome }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums">{{ $franquia->quantidade }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums">{{ $utilizadas }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums">{{ max(0, $franquia->quantidade - $utilizadas) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="tabela-vazia">Solicite à sua equipe comercial a configuração das franquias deste plano.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="cartao overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Faturas em aberto</h2>
                <a class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" href="{{ route('empresa.faturas') }}">Ver todas</a>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[28rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Período</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Vencimento</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($emAberto as $fatura)
                            <tr>
                                <td class="px-5 py-4 text-left">{{ $fatura->competenciaRotulo() }}</td>
                                <td class="px-5 py-4 text-left"><span class="etiqueta {{ $pagamento[$fatura->situacao_pagamento] ?? 'etiqueta-neutra' }}">{{ $fatura->situacao_pagamento }}</span></td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->totalRotulo() }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $fatura->vencimento()->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabela-vazia">Nenhuma fatura em aberto.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="cartao mt-6 overflow-hidden">
        <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Últimas consultas</h2>
            <a class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" href="{{ route('empresa.consultas') }}">Ver todas</a>
        </div>

        <div class="overflow-x-auto">
            <table class="tabela min-w-[40rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Quando</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Resultado</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Valor</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Abrir</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($ultimas as $consulta)
                        <tr>
                            <td class="px-5 py-4 text-left whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $consulta->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $consulta->servico->nome }}</td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ $consulta->deuCerto() ? 'etiqueta-sucesso' : 'etiqueta-erro' }}">
                                    {{ $consulta->deuCerto() ? 'Concluída' : 'Não concluída' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $consulta->preco_cents > 0 ? Dinheiro::brl($consulta->preco_cents) : 'Sem cobrança' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <x-avalia.botao variante="secundario" tamanho="sm" :href="route('empresa.consultas.ver', $consulta)">Ver</x-avalia.botao>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabela-vazia">
                            Nenhuma consulta ainda.
                            <a class="font-medium text-brand-600 dark:text-brand-400" href="{{ route('empresa.consultar') }}">Fazer a primeira</a>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
