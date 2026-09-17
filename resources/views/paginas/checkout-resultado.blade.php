@extends('layouts.fullscreen-layout', ['title' => 'Sua compra'])

@php
    use App\Support\Dinheiro;
    use App\Support\Documento;
@endphp

@section('content')
    <div class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-white/90">
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex h-[60px] w-full max-w-2xl items-center px-6">
                <span class="inline-flex items-center gap-2.5">
                    <x-avalia.logotipo :tamanho="30" texto="1.15rem" />
                    <span class="etiqueta bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">360</span>
                </span>
            </div>
        </header>

        <main class="mx-auto w-full max-w-2xl px-6 py-10">
            @if ($pedido->situacao === 'reprovado')
                {{-- O motivo tecnico fica gravado no pedido para auditoria. A
                     tela diz o suficiente para a pessoa entender e procurar o
                     vendedor, e nao a regra que a recusou: detalhar a regra
                     ensina quem quiser contorna-la. --}}
                <div class="cartao p-6 lg:p-8">
                    <h1 class="text-xl font-semibold tracking-tight">Não foi possível aprovar esta compra</h1>
                    <p class="mt-3 text-gray-500 dark:text-gray-400">
                        A análise não aprovou o parcelamento desta proposta. Se quiser entender melhor,
                        fale com {{ $pedido->produtor->nome }}, que vendeu este produto.
                    </p>
                </div>
            @else
                <div class="cartao p-6 lg:p-8">
                    <span class="etiqueta etiqueta-sucesso">Compra registrada</span>

                    <h1 class="mt-4 text-xl font-semibold tracking-tight">{{ $pedido->oferta->titulo }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $pedido->cliente_nome }} · {{ Documento::mascarar($pedido->cliente_documento) }}
                    </p>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Total</dt>
                            <dd class="font-semibold">{{ Dinheiro::brl($pedido->valor_total_cents) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Entrada</dt>
                            <dd class="font-semibold">{{ Dinheiro::brl($pedido->entrada_cents) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Parcelas</dt>
                            <dd class="font-semibold">
                                {{ $pedido->parcelas }}x de {{ Dinheiro::brl($pedido->valor_parcela_cents) }}
                            </dd>
                        </div>
                    </dl>

                    @if ($pedido->parcelas < $pedido->oferta->parcelas)
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            O parcelamento foi ajustado para {{ $pedido->parcelas }} vezes.
                        </p>
                    @endif
                </div>

                <div class="cartao mt-6 p-6 lg:p-8">
                    <h2 class="font-semibold">Pague a entrada para liberar o parcelamento</h2>

                    @if ($entrada?->cobranca?->invoice_url)
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Vence em {{ $entrada->vencimento->format('d/m/Y') }}.
                            As parcelas só são emitidas depois que a entrada for confirmada.
                        </p>

                        <a href="{{ $entrada->cobranca->invoice_url }}" target="_blank" rel="noopener noreferrer"
                           class="botao botao-primario mt-5 w-full sm:w-auto">
                            Abrir o boleto de {{ Dinheiro::brl($entrada->valor_cents) }}
                        </a>
                    @else
                        {{-- Cobranca que nao saiu nao derruba a venda: o pedido
                             esta gravado e o boleto se emite de novo. --}}
                        <div class="aviso aviso-alerta mt-4">
                            O boleto ainda está sendo gerado. Em alguns minutos ele chega no seu e-mail,
                            em {{ $pedido->cliente_email }}.
                        </div>
                    @endif
                </div>
            @endif

            <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-500">
                Guarde este endereço para acompanhar a compra.
            </p>
        </main>
    </div>
@endsection
