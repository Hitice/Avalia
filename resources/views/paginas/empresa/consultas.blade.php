@extends('layouts.app', ['title' => 'Consultas'])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Consultas</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $empresa->razao_social }}</p>
        </div>
        <x-avalia.botao :href="route('empresa.consultar')">Nova consulta</x-avalia.botao>
    </div>

    <x-avalia.filtro-consultas :acao="route('empresa.consultas')" :servicos="$servicos" :escolha="$escolha" />

    {{-- Tentativa e cobranca separadas: consulta que falhou aparece na lista e
         nao entra no valor. Sem essa distincao o cliente soma a tela, nao bate
         com a fatura e abre chamado. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
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
            <span class="rotulo-grupo block">Valor no período</span>
            <span class="mt-1 block font-semibold text-gray-800 dark:text-white/90 tabular-nums">{{ Dinheiro::brl($resumo['valor_cents']) }}</span>
        </div>
    </div>

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[44rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Quando</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Serviço</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Finalidade</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Resultado</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Valor</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Abrir</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($consultas as $consulta)
                        <tr>
                            <td class="px-5 py-4 text-left whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $consulta->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $consulta->servico->nome }}</td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">{{ $consulta->finalidade }}</td>
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
                        <tr><td colspan="6" class="tabela-vazia">Nenhuma consulta no período escolhido.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$consultas" />
    </div>
@endsection
