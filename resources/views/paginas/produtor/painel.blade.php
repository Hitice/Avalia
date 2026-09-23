@extends('layouts.fullscreen-layout', ['title' => 'Painel do produtor'])

@php
    use App\Support\Dinheiro;
@endphp

@section('content')
    <div class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-white/90">
        <header class="relative border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex h-[60px] w-full max-w-[87rem] items-center justify-between px-6">
                <span class="inline-flex items-center gap-2.5">
                    <x-avalia.logotipo :tamanho="30" texto="1.15rem" marca="cobranca" />
                    <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
                </span>

                <div class="flex items-center gap-4 pr-12 text-sm sm:pr-14 min-[1550px]:pr-0">
                    <span class="hidden text-gray-500 sm:inline dark:text-gray-400">{{ $produtor->nome }}</span>
                    <form method="POST" action="{{ route('produtor.sair') }}">
                        @csrf
                        <button type="submit" class="text-gray-500 hover:text-brand-500 dark:text-gray-400">Sair</button>
                    </form>
                </div>
            </div>

            {{-- Na ponta extrema, fora do alinhamento das colunas, como na
                 pagina inicial: e ferramenta da pagina, e nao passo do funil.
                 O respiro a direita do nav reserva o lugar dele. --}}
            <x-avalia.tema class="absolute top-1/2 right-3 size-11 -translate-y-1/2 sm:right-4" />
        </header>

        <main class="mx-auto w-full max-w-[87rem] px-6 py-8">
            @unless ($produtor->podeVender())
                {{-- O que falta, dito no lugar onde a pessoa chega. Cadastro
                     pendente sem explicacao vira e-mail perguntando "e agora?". --}}
                <div class="aviso aviso-alerta">
                    <strong>Cadastro em análise.</strong>
                    Assim que a sua conta de recebimento for aberta, você poderá publicar ofertas e vender parcelado.
                    A equipe do {{ \App\Support\Empresa::marcaCobranca() }} entra em contato pelo WhatsApp cadastrado.
                </div>
            @endunless

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="cartao p-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Já recebido</p>
                    <p class="mt-1 text-2xl font-semibold">{{ Dinheiro::brl($recebidoCents) }}</p>
                </div>
                <div class="cartao p-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">A receber</p>
                    <p class="mt-1 text-2xl font-semibold">{{ Dinheiro::brl($aReceberCents) }}</p>
                </div>
                <div class="cartao p-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Em atraso</p>
                    <p class="mt-1 text-2xl font-semibold {{ $vencidoCents > 0 ? 'text-error-600 dark:text-error-400' : '' }}">
                        {{ Dinheiro::brl($vencidoCents) }}
                    </p>
                </div>
            </div>

            <div class="cartao mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-semibold">Últimas vendas</h2>
                </div>

                <div class="tabela-rolagem">
                    <table class="tabela">
                        <thead class="tabela-cabecalho">
                            <tr>
                                <th class="tabela-th">Cliente</th>
                                <th class="tabela-th">Oferta</th>
                                <th class="tabela-th">Valor</th>
                                <th class="tabela-th">Parcelas</th>
                                <th class="tabela-th">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pedidos as $pedido)
                                <tr>
                                    <td class="tabela-td">{{ $pedido->cliente_nome }}</td>
                                    <td class="tabela-td">{{ $pedido->oferta->titulo }}</td>
                                    <td class="tabela-td">{{ Dinheiro::brl($pedido->valor_total_cents) }}</td>
                                    <td class="tabela-td">{{ $pedido->parcelas }}x</td>
                                    <td class="tabela-td">
                                        <span class="etiqueta {{ $pedido->situacao === 'efetivado' ? 'etiqueta-sucesso' : 'etiqueta-neutra' }}">
                                            {{ str_replace('_', ' ', $pedido->situacao) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="tabela-vazia">Nenhuma venda ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
@endsection
