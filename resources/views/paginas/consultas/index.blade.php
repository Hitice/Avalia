@extends('layouts.app', ['title' => 'Consultas'])

@php
    use App\Support\Dinheiro;
    use App\Support\Rotulos;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Consultas</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Todas as empresas contratantes</p>
    </div>

    <x-avalia.filtro-consultas :acao="route('consultas')" :servicos="$servicos" :escolha="$escolha" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consultas no período</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ $resumo['total'] }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Não concluídas</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ $resumo['falhas'] }}</span>
            <span class="ajuda-campo">Não geram cobrança.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Consumo no período</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($resumo['valor_cents']) }}</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Espera do cliente</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">
                {{ Rotulos::espera($saude['tempo_medio_ms']) }}
            </span>
            <span class="ajuda-campo">Tempo médio até a resposta chegar.</span>
        </div>
    </div>

    @if ($saude['por_servico']->isNotEmpty())
        <div class="cartao mb-6 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Consultas não concluídas por serviço</h2>
                <p class="ajuda-campo mt-1">Não são cobradas da empresa. Concentração em um serviço é assunto para o fornecedor.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[28rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Consultas</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Não concluídas</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Proporção</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($saude['por_servico'] as $linha)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $linha->servico?->nome ?? 'Serviço descontinuado' }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $linha->total }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $linha->falhas }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">
                                    {{ $linha->total > 0 ? number_format($linha->falhas * 100 / $linha->total, 1, ',', '.').'%' : '0,0%' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[48rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Quando</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Resultado</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Espera</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Valor</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium"><span class="sr-only">Ações</span></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($consultas as $consulta)
                        <tr>
                            <td class="px-5 py-4 text-left whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $consulta->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">
                                @if ($consulta->cliente)
                                    {{ $consulta->cliente->razao_social }}
                                @elseif ($consulta->vendedor_id)
                                    {{-- Sem cliente E com quem consultou: é consulta da
                                         própria casa, não empresa que sumiu. Rótulo errado
                                         em tela de operação vira diagnóstico errado. --}}
                                    <span class="text-gray-500 dark:text-gray-400">Consulta da casa</span>
                                    <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500">
                                        {{ $consulta->solicitante }}
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Empresa encerrada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">{{ $consulta->servico?->nome ?? 'Serviço descontinuado' }}</td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ $consulta->deuCerto() ? 'etiqueta-sucesso' : 'etiqueta-erro' }}">
                                    {{ $consulta->deuCerto() ? 'Concluída' : 'Não concluída' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $consulta->duracao_ms ? Rotulos::espera($consulta->duracao_ms) : '' }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $consulta->preco_cents > 0 ? Dinheiro::brl($consulta->preco_cents) : 'Sem cobrança' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                {{-- Abrir e emitir o laudo só existem para consulta concluída
                                     e não expurgada: nas outras não há conteúdo, e um botão
                                     que leva a lugar nenhum ensina a ignorar o botão. --}}
                                @if ($consulta->deuCerto() && ! $consulta->expurgada())
                                    <div class="flex items-center justify-end gap-1">
                                        <a class="acao-linha" href="{{ route('consultas.ver', $consulta) }}"
                                           title="Abrir o resultado (a abertura fica na trilha)">
                                            <x-avalia.icone nome="consulta" />
                                            <span class="sr-only">Abrir o resultado</span>
                                        </a>

                                        <a class="acao-linha" href="{{ route('consultas.pdf', $consulta) }}"
                                           target="_blank" rel="noopener" title="Abrir o laudo em PDF">
                                            <x-avalia.icone nome="documento" />
                                            <span class="sr-only">Baixar o laudo</span>
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tabela-vazia">Nenhuma consulta no período escolhido.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$consultas" />
    </div>

    {{-- O documento consultado nao aparece na lista. Quem precisa dele abre a
         consulta, e a abertura fica registrada: acesso a dado pessoal e evento,
         nao coluna de tabela. --}}
@endsection
