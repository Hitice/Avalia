@extends('layouts.app', ['title' => 'Calculadora'])

@php
    use App\Support\Dinheiro;

    $lucro = $mes['lucro_cents'];
    $temAdesao = $adesao['parcela_cents'] > 0;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Calculadora de lucro</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Quanto um contrato deixa por mês, antes de assinar. Nada é gravado, e o endereço
            guarda o cenário: copie a barra do navegador para mostrar a simulação a alguém.
        </p>
    </div>

    @include('paginas.catalogo.abas', ['atual' => 'calculadora'])

    @if (! $catalogo || $faixas === [])
        <div class="aviso aviso-alerta">
            Sem catálogo não há faixa nem custo para simular.
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
            <div class="cartao p-6">
                <form method="GET" action="{{ route('catalogo.calculadora') }}" class="grid gap-5">
                    <div>
                        <label for="faixa" class="rotulo-campo">Faixa contratada</label>
                        <select id="faixa" name="faixa" class="campo">
                            @foreach ($faixas as $opcao)
                                <option value="{{ Dinheiro::numero($opcao) }}" @selected($opcao === $faixa)>
                                    {{ Dinheiro::faixa($opcao) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="consumo" class="rotulo-campo">Consumo estimado no mês</label>
                        <input id="consumo" name="consumo" type="text" inputmode="decimal" class="campo"
                               value="{{ Dinheiro::numero($entrada['consumo']) }}">
                        <span class="ajuda-campo">O que o cliente deve consultar de fato.</span>
                    </div>

                    <div>
                        <label for="mensalidade" class="rotulo-campo">Mensalidade</label>
                        <input id="mensalidade" name="mensalidade" type="text" inputmode="decimal" class="campo"
                               value="{{ Dinheiro::numero($entrada['mensalidade']) }}">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="adesao" class="rotulo-campo">Taxa de adesão</label>
                            <input id="adesao" name="adesao" type="text" inputmode="decimal" class="campo"
                                   value="{{ $entrada['adesao'] === 0 ? '' : Dinheiro::numero($entrada['adesao']) }}"
                                   placeholder="0,00">
                        </div>
                        <div>
                            <label for="parcelas" class="rotulo-campo">Parcelas</label>
                            <input id="parcelas" name="parcelas" type="number" min="1" max="24" class="campo"
                                   value="{{ $entrada['parcelas'] }}">
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="excedente" value="0">
                        <input type="checkbox" name="excedente" value="1"
                               class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked($entrada['excedente'])>
                        Houve excedente no mês
                    </label>
                    <span class="ajuda-campo -mt-3">
                        A comissão do mês inteiro sobe de 10% para 20%.
                    </span>

                    <x-avalia.botao>Calcular</x-avalia.botao>
                </form>
            </div>

            <div class="grid gap-6">
                <div class="cartao p-6">
                    <div class="mb-5 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-medium text-gray-800 dark:text-white/90">No mês</h2>
                        @if ($plano)
                            <span class="text-xs text-gray-500 dark:text-gray-400">plano {{ $plano->nome }}</span>
                        @endif
                    </div>

                    <table class="tabela">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td class="py-3 text-left text-gray-600 dark:text-gray-300">
                                    Fatura do cliente
                                    <span class="ajuda-campo block">
                                        mensalidade mais {{ Dinheiro::brl($mes['consumo_faturado_cents']) }} de consumo
                                        @if ($mes['pagou_sem_usar_cents'] > 0)
                                            <span class="etiqueta etiqueta-neutra ml-1">piso do mínimo</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="py-3 text-right font-medium tabular-nums whitespace-nowrap text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl($mes['fatura_cents']) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 text-left text-gray-600 dark:text-gray-300">
                                    Imposto
                                    <span class="ajuda-campo block">{{ $catalogo->impostoRotulo() }} sobre a fatura inteira</span>
                                </td>
                                <td class="py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    − {{ Dinheiro::brl($mes['imposto_cents']) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 text-left text-gray-600 dark:text-gray-300">
                                    Custo do fornecedor
                                    <span class="ajuda-campo block">
                                        @if ($entrada['custo_bps'] === null)
                                            nenhum custo cadastrado nesta faixa
                                        @else
                                            {{ number_format($entrada['custo_bps'] / 100, 1, ',', '.') }}% do consumo,
                                            pela tabela; só sobre o que foi consultado de fato
                                        @endif
                                    </span>
                                </td>
                                <td class="py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    − {{ Dinheiro::brl($mes['custo_cents']) }}
                                </td>
                            </tr>
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-3 text-left text-gray-600 dark:text-gray-300">
                                    Lucro antes da comissão
                                </td>
                                <td class="py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    {{ Dinheiro::brl($mes['lucro_antes_comissao_cents']) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 text-left text-gray-600 dark:text-gray-300">
                                    Comissão do vendedor
                                    <span class="ajuda-campo block">
                                        {{ $entrada['excedente'] ? '20' : '10' }}% do lucro
                                    </span>
                                </td>
                                <td class="py-3 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    − {{ Dinheiro::brl($mes['comissao_cents']) }}
                                </td>
                            </tr>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700">
                                <td class="py-4 text-left font-medium text-gray-800 dark:text-white/90">
                                    Projeção
                                </td>
                                <td class="py-4 text-right text-lg font-semibold tabular-nums whitespace-nowrap
                                           {{ $lucro < 0 ? 'text-error-600 dark:text-error-400' : 'text-success-600 dark:text-success-500' }}">
                                    {{ Dinheiro::brl($lucro) }}
                                    @if ($mes['margem_pct'] !== null)
                                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">
                                            {{ number_format($mes['margem_pct'], 1, ',', '.') }}% da fatura
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @if ($mes['pagou_sem_usar_cents'] > 0)
                        <div class="aviso aviso-ok mt-5">
                            O cliente paga {{ Dinheiro::brl($mes['pagou_sem_usar_cents']) }} de consumo que não usou.
                            Isso não gera custo de fornecedor, então entra quase inteiro no lucro.
                        </div>
                    @endif

                    @if ($lucro < 0)
                        <div class="aviso aviso-erro mt-5">
                            Neste cenário o contrato dá prejuízo. Suba a faixa, o preço ou a mensalidade.
                        </div>
                    @endif
                </div>

                @if ($temAdesao)
                    <div class="cartao p-6">
                        <h2 class="mb-1 font-medium text-gray-800 dark:text-white/90">Adesão</h2>
                        <p class="ajuda-campo mb-5">
                            Rateio de metade para cada lado, parcela a parcela, conforme forem liquidadas.
                        </p>

                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <span class="rotulo-grupo block">Parcela</span>
                                <span class="mt-1 block font-medium text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl($adesao['parcela_cents']) }}
                                </span>
                            </div>
                            <div>
                                <span class="rotulo-grupo block">Avalia</span>
                                <span class="mt-1 block font-medium text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl($adesao['avalia_cents']) }}
                                </span>
                            </div>
                            <div>
                                <span class="rotulo-grupo block">Vendedor</span>
                                <span class="mt-1 block font-medium text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl($adesao['vendedor_cents']) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endif

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    O custo vem da tabela: custo e preço de venda são fixos por serviço, então a proporção
                    entre eles já está decidida e vale para o consumo total, qualquer que seja o mix.
                    Cliente que só consulte os serviços de margem mais apertada rende menos que isto.
                </p>
            </div>
        </div>
    @endif
@endsection
