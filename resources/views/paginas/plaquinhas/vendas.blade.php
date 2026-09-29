@extends('layouts.app', ['title' => 'Vendas QR'])

@php
    use App\Support\Dinheiro;

    $maiorDaSerie = max(1, $serie->max('placas'));
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Vendas QR</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Placas vendidas em {{ $mes->translatedFormat('F \d\e Y') }}, já com o reparte aplicado.
            </p>
        </div>

        {{-- Seletor por GET, sem JavaScript: a tela inteira é leitura, e o mês
             escolhido precisa sobreviver a um F5 e a um link colado para o
             outro sócio conferir o mesmo número. --}}
        {{-- `campo-linha` e estilo de input, e nao de layout: no <form> ele
             virava uma caixa com borda e altura fixa, com o rotulo e o select
             transbordando dela. O layout aqui e flex, e o estilo fica no
             select, que e o campo de verdade. --}}
        <form method="GET" action="{{ route('plaquinhas.vendas') }}" class="flex items-end gap-2">
            <div>
                <label for="mes" class="rotulo-campo">Mês</label>
                <select id="mes" name="mes" class="campo-linha" onchange="this.form.submit()">
                    @foreach ($meses as $opcao)
                        <option value="{{ $opcao->format('Y-m') }}" @selected($opcao->format('Y-m') === $mes->format('Y-m'))>
                            {{ $opcao->translatedFormat('F/Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="botao botao-secundario botao-sm">Ver</button></noscript>
        </form>
    </div>

    @if (! empty($sociosAusentes))
        {{-- Sócio configurado sem conta não pode falhar calado: a divisão
             continuaria fechando, só que entre menos gente, e o erro apareceria
             no bolso de alguém. --}}
        <div class="aviso aviso-erro mb-6">
            <p class="font-medium">A divisão está incompleta.</p>
            <p class="mt-1">
                {{ count($sociosAusentes) === 1 ? 'Este e-mail não tem conta' : 'Estes e-mails não têm conta' }}
                na equipe: {{ implode(', ', $sociosAusentes) }}. Enquanto isso, a sobra está sendo dividida
                só entre quem foi encontrado. Ajuste <code>ETIQUETAS_SOCIOS</code> ou cadastre a conta.
            </p>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-avalia.cartao-indicador rotulo="Placas vendidas" :valor="$placas"
                                   :href="route('etiquetas.index')"
                                   :ajuda="$semVendedor > 0
                                       ? $semVendedor.($semVendedor === 1 ? ' sem vendedor identificado.' : ' sem vendedor identificado.')
                                       : null" />

        <x-avalia.cartao-indicador rotulo="Faturamento" :valor="Dinheiro::brl($totais['bruto'])"
                                   ajuda="O que foi cobrado, pelo valor gravado em cada venda." />

        <x-avalia.cartao-indicador rotulo="Custo das placas" :valor="Dinheiro::brl($totais['custo'])"
                                   ajuda="Desembolso da casa. Sai antes de qualquer comissão." />

        <x-avalia.cartao-indicador rotulo="Líquido" :valor="Dinheiro::brl($totais['liquido'])"
                                   ajuda="Faturamento menos custo. É sobre isto que a comissão incide." />

        <x-avalia.cartao-indicador rotulo="Comissões" :valor="Dinheiro::brl($totais['comissao'])"
                                   :ajuda="config('etiquetas.comissao_pct').'% do líquido. Venda de sócio não gera comissão.'" />

        <x-avalia.cartao-indicador rotulo="Sobra a dividir" :valor="Dinheiro::brl($totais['sobra'])"
                                   tom="text-brand-600 dark:text-brand-400"
                                   ajuda="O que resta depois das comissões." />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Divisão entre sócios</h2>
                <p class="ajuda-campo mt-1">
                    A sobra do mês, repartida em partes iguais. O centavo ímpar fica sempre com o primeiro da lista.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[22rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Sócio</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">A receber</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($porSocio as $socio)
                            <tr>
                                <td class="px-5 py-4 text-left">
                                    <span class="block text-gray-800 dark:text-white/90">{{ $socio['nome'] }}</span>
                                    <span class="ajuda-campo">{{ $socio['email'] }}</span>
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-white/90">
                                    {{ Dinheiro::brl($socio['cents']) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="tabela-vazia">Nenhum sócio configurado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Por vendedor</h2>
                <p class="ajuda-campo mt-1">Quem vendeu no mês e o que cada um tem a receber.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[26rem]">
                    <thead class="tabela-cabecalho"><tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Vendedor</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Placas</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Vendeu</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Comissão</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($porVendedor as $linha)
                            <tr>
                                <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">
                                    {{ $linha['nome'] }}
                                    @if ($linha['eh_socio'])
                                        <span class="etiqueta etiqueta-neutra ml-1">sócio</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $linha['placas'] }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ Dinheiro::brl($linha['bruto']) }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-white/90">
                                    {{ $linha['eh_socio'] ? '-' : Dinheiro::brl($linha['comissao']) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabela-vazia">Nenhuma placa vendida neste mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-6 cartao p-6">
        <h2 class="font-medium text-gray-800 dark:text-white/90">Placas vendidas por mês</h2>
        <p class="ajuda-campo mt-1">Últimos doze meses. Serve para ver a tendência, não para conferir repasse.</p>

        {{-- Barras em CSS puro, sem biblioteca de gráfico: são doze valores, e
             carregar um pacote inteiro para desenhar doze retângulos custa mais
             no carregamento da página do que entrega em leitura. --}}
        <div class="mt-6 flex h-40 items-end gap-1 sm:gap-2">
            @foreach ($serie as $ponto)
                <div class="flex flex-1 flex-col items-center gap-2">
                    <div class="flex w-full flex-1 items-end">
                        <div class="w-full rounded-t bg-brand-500 transition-all dark:bg-brand-500/80"
                             style="height: {{ max(2, round($ponto['placas'] / $maiorDaSerie * 100)) }}%"
                             title="{{ $ponto['rotulo'] }}: {{ $ponto['placas'] }} {{ $ponto['placas'] === 1 ? 'placa' : 'placas' }} · {{ Dinheiro::brl($ponto['bruto']) }}"></div>
                    </div>
                    <span class="text-[10px] tabular-nums text-gray-500 dark:text-gray-400">{{ $ponto['placas'] }}</span>
                    <span class="text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500">{{ $ponto['rotulo'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 cartao overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Últimas vendas do mês</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="tabela min-w-[36rem]">
                <thead class="tabela-cabecalho"><tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Código</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Cliente</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium">Vendedor</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Valor</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Vendida em</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($ultimas as $venda)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <a href="{{ route('etiquetas.ficha', $venda) }}" class="font-mono text-brand-600 hover:underline dark:text-brand-400">
                                    {{ $venda->codigo }}
                                </a>
                            </td>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">{{ $venda->cliente_nome ?? '-' }}</td>
                            <td class="px-5 py-4 text-left text-gray-500 dark:text-gray-400">
                                {{ $venda->vendedor?->nome ?? 'Não identificado' }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl((int) $venda->valor_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $venda->vendida_em->format('d/m/Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabela-vazia">Nenhuma placa vendida neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
