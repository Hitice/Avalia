@extends('layouts.app', ['title' => 'Simulador'])

@php
    use App\Support\Dinheiro;

    $reais = fn (?int $centavos) => $centavos === null ? '' : number_format($centavos / 100, 2, ',', '');
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $vendedor->ehAdmin() ? 'Simulador' : 'Minha carteira' }}
        </h1>
        @if ($vendedor->ehAdmin())
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Quanto o contrato custa para o cliente e quanto sobra para o vendedor.
            </p>
        @endif
    </div>

    {{-- Cada papel entra pelo seu modulo: o vendedor pela carteira, a
         administracao por Simulador. A tela e a mesma, a navegacao nao. --}}
    @if ($vendedor->ehAdmin())
        @include('paginas.simulacao.abas', ['atual' => 'proposta'])
    @else
        @include('paginas.carteira.abas')
    @endif

    @if ($faixas === [])
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">Nenhuma faixa de preço no catálogo vigente.</p>
        </div>
    @else
        {{-- Tudo por GET e nada e gravado: simulacao nao e proposta. O endereco
             carrega o cenario inteiro, entao a simulacao vira link em vez de
             captura de tela. --}}
        <form method="GET" action="{{ route('carteira.simulacao') }}" class="cartao mb-6 p-6">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="faixa" class="rotulo-campo">Tabela de preços</label>
                    <select id="faixa" name="faixa" class="campo">
                        @foreach ($faixas as $opcao)
                            <option value="{{ $reais($opcao) }}" @selected($opcao === $faixa)>{{ Dinheiro::faixa($opcao) }}</option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">{{ $plano?->nome ?? 'Sem plano cadastrado nesta faixa' }}</span>
                </div>

                <div>
                    <label for="mensalidade" class="rotulo-campo">Mensalidade</label>
                    <input id="mensalidade" name="mensalidade" type="text" class="campo"
                           inputmode="decimal" value="{{ $reais($entrada['mensalidade']) }}">
                </div>

                <div>
                    <label for="consumo" class="rotulo-campo">Consumo estimado no mês</label>
                    <input id="consumo" name="consumo" type="text" class="campo"
                           inputmode="decimal" value="{{ $reais($entrada['consumo']) }}">
                </div>

                <div>
                    <label for="minimo" class="rotulo-campo">Consumo mínimo negociado</label>
                    <input id="minimo" name="minimo" type="text" class="campo"
                           inputmode="decimal" value="{{ $reais($entrada['minimo']) }}">
                    <span class="ajuda-campo">Piso de cobrança em qualquer valor; a tabela acima define os preços.</span>
                    <span class="ajuda-campo">Abaixo do mínimo, a empresa paga o mínimo.</span>
                </div>

                <div>
                    <label for="adesao" class="rotulo-campo">Taxa de adesão</label>
                    <input id="adesao" name="adesao" type="text" class="campo"
                           inputmode="decimal" value="{{ $reais($entrada['adesao']) }}">
                </div>

                <div>
                    <label for="parcelas" class="rotulo-campo">Parcelas da adesão</label>
                    <input id="parcelas" name="parcelas" type="number" min="1" max="12" class="campo"
                           value="{{ $entrada['parcelas'] }}">
                </div>

                <div class="flex items-end">
                    <x-avalia.botao>Simular</x-avalia.botao>
                </div>
            </div>
        </form>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="cartao overflow-hidden">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h2 class="font-medium text-gray-800 dark:text-white/90">O que a empresa paga por mês</h2>
                </div>
                <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div class="flex items-center justify-between px-6 py-4">
                        <dt class="text-gray-600 dark:text-gray-300">Mensalidade</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($proposta['mensalidade_cents']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <dt class="text-gray-600 dark:text-gray-300">Consumo cobrado</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($proposta['consumo_faturado_cents']) }}</dd>
                    </div>
                    @if ($proposta['pagou_sem_usar_cents'] > 0)
                        <div class="flex items-center justify-between px-6 py-4">
                            <dt class="text-gray-600 dark:text-gray-300">
                                Consumo mínimo não utilizado
                                <span class="ajuda-campo block">Parte do mínimo que a empresa paga sem consumir.</span>
                            </dt>
                            <dd class="tabular-nums text-gray-500 dark:text-gray-400">{{ Dinheiro::brl($proposta['pagou_sem_usar_cents']) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between bg-gray-50 px-6 py-4 dark:bg-white/[0.03]">
                        <dt class="font-medium text-gray-800 dark:text-white/90">Total da fatura</dt>
                        <dd class="tabular-nums font-semibold text-gray-800 dark:text-white/90">{{ Dinheiro::brl($proposta['fatura_cents']) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="cartao overflow-hidden">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h2 class="font-medium text-gray-800 dark:text-white/90">O que você recebe</h2>
                    <p class="ajuda-campo mt-1">Comissão de {{ $pctComissao }}%, apurada no fechamento da competência.</p>
                </div>
                <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div class="flex items-center justify-between px-6 py-4">
                        <dt class="text-gray-600 dark:text-gray-300">Comissão do mês</dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($proposta['comissao_cents']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <dt class="text-gray-600 dark:text-gray-300">
                            Parte da adesão
                            <span class="ajuda-campo block">Metade da parcela, conforme ela for liquidada.</span>
                        </dt>
                        <dd class="tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($adesaoDoVendedor) }}</dd>
                    </div>
                    <div class="flex items-center justify-between bg-gray-50 px-6 py-4 dark:bg-white/[0.03]">
                        <dt class="font-medium text-gray-800 dark:text-white/90">Total no primeiro mês</dt>
                        <dd class="tabular-nums font-semibold text-gray-800 dark:text-white/90">
                            {{ Dinheiro::brl($proposta['comissao_cents'] + $adesaoDoVendedor) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Valores estimados. A comissão é apurada sobre a competência fechada e liberada após a
            liquidação da fatura.
        </p>
    @endif
@endsection
