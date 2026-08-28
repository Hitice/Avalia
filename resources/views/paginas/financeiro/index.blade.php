@extends('layouts.app', ['title' => 'Financeiro'])

@php
    use App\Models\Fatura;
    use App\Support\Dinheiro;
    use App\Support\Rotulos;

    $filtros = collect(['' => 'Todas'] + array_combine(
        Fatura::SITUACOES_PAGAMENTO,
        array_map([Rotulos::class, 'fatura'], Fatura::SITUACOES_PAGAMENTO),
    ))
        ->map(fn ($rotulo, $chave) => [
            'rotulo' => $rotulo,
            'url' => route('financeiro.index', array_filter(['situacao' => $chave])),
        ])
        ->all();
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Financeiro</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Acompanhe as faturas de todos os clientes e confirme os pagamentos recebidos.
            </p>
        </div>

        {{-- Exporta o recorte que está na tela, com os números internos. É a
             planilha do contador e da conciliação, e por isso o nome do arquivo
             diz "interno": ela não pode ser encaminhada a cliente nem a
             vendedor. --}}
        <x-avalia.botao variante="secundario" :href="route('financeiro.planilha', request()->query())"
                        title="Planilha interna: leva custo, lucro e comissão">
            Exportar
        </x-avalia.botao>
    </div>

    @include('paginas.catalogo.avisos')

    {{-- Os cartões separam três perguntas que a tela misturava numa só:
         quanto entrou, quanto vai entrar e quanto sai. Os números vêm de
         App\Support\Caixa, a mesma fonte da visão geral. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Recebido no mês</span>
            <span class="mt-1 block text-xl font-semibold tabular-nums text-success-600 dark:text-success-500">
                {{ Dinheiro::brl($totais['recebido_no_mes']) }}
            </span>
            <span class="ajuda-campo">Pela data da baixa, não pela competência.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">A receber</span>
            <span class="mt-1 block text-xl font-semibold tabular-nums text-gray-800 dark:text-white/90">
                {{ Dinheiro::brl($totais['a_receber']) }}
            </span>
            <span class="ajuda-campo">Em aberto e vencido somados.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Vencido</span>
            <span class="mt-1 block text-xl font-semibold tabular-nums {{ $totais['vencido'] > 0 ? 'text-error-600 dark:text-error-400' : 'text-gray-800 dark:text-white/90' }}">
                {{ Dinheiro::brl($totais['vencido']) }}
            </span>
            <span class="ajuda-campo">Já dentro do valor a receber.</span>
        </div>
        <div class="cartao p-5">
            <span class="rotulo-grupo block">Comissão a repassar</span>
            <span class="mt-1 block text-xl font-semibold tabular-nums text-gray-800 dark:text-white/90">
                {{ Dinheiro::brl($totais['a_repassar']) }}
            </span>
            <span class="ajuda-campo">Já liberada, líquida das demonstrações.</span>
        </div>
    </div>

    <p class="ajuda-campo mb-6">
        Total liquidado desde o início: {{ Dinheiro::brl($totais['liquidado']) }}.
    </p>

    <div class="mb-6">
        <x-avalia.segmentado rotulo="Faturas" :atual="$situacao ?? ''" :itens="$filtros" />
    </div>

    <x-avalia.filtro-faturas :acao="route('financeiro.index')" :vendedores="$vendedores"
                             :competencias="$competencias" :escolha="$escolha" />

    {{-- A tabela inteira dentro de um formulario, para a selecao das linhas
         virar acao em lote. No fechamento a operacao repete a mesma coisa
         dezenas de vezes, e uma a uma cansa e erra.

         A baixa de pagamento NAO entra no lote de propósito: ela exige
         justificativa por fatura e libera comissão na hora, e uma baixa em lote
         seria a porta mais larga do sistema para dinheiro dado como recebido
         sem ter entrado. --}}
    <form method="POST" action="{{ route('financeiro.lote') }}" x-data="{ marcadas: [] }">
        @csrf

        <div class="cartao overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-800"
                 x-show="marcadas.length > 0" x-cloak>
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    <span x-text="marcadas.length"></span>
                    <span x-text="marcadas.length === 1 ? 'fatura selecionada' : 'faturas selecionadas'"></span>
                </span>

                <div class="flex flex-wrap items-center gap-2">
                    <x-avalia.botao variante="secundario" tamanho="sm" type="submit" name="acao" value="exportar">
                        Exportar selecionadas
                    </x-avalia.botao>

                    <x-avalia.botao variante="secundario" tamanho="sm" type="submit" name="acao" value="reenviar"
                                    x-on:click="if (! confirm('Reenviar a cobrança por e-mail para ' + marcadas.length + ' cliente(s)?')) $event.preventDefault()">
                        Reenviar cobrança
                    </x-avalia.botao>
                </div>
            </div>

        <div class="overflow-x-auto">
            <table class="tabela min-w-[64rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">
                            {{-- Marcar tudo age sobre o RECORTE que está na tela, e não
                                 sobre a base: é o mesmo princípio da exportação. --}}
                            <input type="checkbox" class="caixa" title="Selecionar todas desta tela"
                                   x-on:change="marcadas = $event.target.checked
                                       ? [...$root.querySelectorAll('[name=\'faturas[]\']')].map(c => (c.checked = true) && c.value)
                                       : ([...$root.querySelectorAll('[name=\'faturas[]\']')].forEach(c => c.checked = false), [])">
                            <span class="sr-only">Selecionar todas</span>
                        </th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Empresa</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Competência</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Pagamento</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Lucro</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Comissão</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Vence</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Confirmar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($faturas as $fatura)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <input type="checkbox" name="faturas[]" class="caixa"
                                       value="{{ $fatura->id }}" x-model="marcadas">
                                <span class="sr-only">Selecionar a fatura de {{ $fatura->cliente->razao_social }}</span>
                            </td>
                            <td class="px-5 py-4 text-left">
                                <a href="{{ route('empresas.ficha', $fatura->cliente) }}"
                                   class="hover:text-brand-500 dark:hover:text-brand-400 font-medium text-gray-800 dark:text-white/90">
                                    {{ $fatura->cliente->razao_social }}
                                </a>
                                @if ($fatura->vendedor)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $fatura->vendedor->nome }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                {{ $fatura->competenciaRotulo() }}
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ Rotulos::faturaEtiqueta($fatura->situacao_pagamento) }}">
                                    {{ Rotulos::fatura($fatura->situacao_pagamento) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right font-medium tabular-nums whitespace-nowrap text-gray-800 dark:text-white/90">
                                {{ $fatura->totalRotulo() }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->lucro_cents) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ Dinheiro::brl($fatura->comissao_cents) }}
                                @if ($fatura->comissao_liberada_em)
                                    <span class="mt-0.5 block text-xs text-success-600 dark:text-success-500">Comissão liberada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $fatura->vencimento()->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                {{-- Uma barra de acoes por linha, no lugar de botoes com rotulo
                                     comprido. A operacao repete a mesma acao dezenas de vezes
                                     seguidas no fechamento, e nesse ritmo o que importa e o
                                     icone estar sempre na mesma posicao. Cada um carrega
                                     `title`, para quem passa o mouse ler o que ele faz. --}}
                                <div class="flex items-center justify-end gap-1">
                                    <a class="acao-linha" href="{{ route('financeiro.pdf', $fatura) }}"
                                       title="Baixar o demonstrativo da fatura">
                                        <x-avalia.icone nome="documento" />
                                        <span class="sr-only">Demonstrativo em PDF</span>
                                    </a>

                                    @if ($fatura->cobrancaAsaas?->bank_slip_url)
                                        <a class="acao-linha" target="_blank" rel="noopener noreferrer"
                                           href="{{ $fatura->cobrancaAsaas->bank_slip_url }}" title="Abrir o boleto">
                                            <x-avalia.icone nome="boleto" />
                                            <span class="sr-only">Abrir o boleto</span>
                                        </a>
                                    @endif

                                    @unless ($fatura->estaLiquidada())
                                        {{-- Fatura sem cobranca no provedor: o fechamento tentou
                                             e nao conseguiu, e sem este botao o boleto so
                                             nasceria mexendo no banco de dados. --}}
                                        @unless ($fatura->cobrancaAsaas?->asaas_charge_id)
                                            <button type="submit" class="acao-linha" title="Emitir a cobrança no provedor"
                                                    form="cobranca-{{ $fatura->id }}">
                                                <x-avalia.icone nome="raio" />
                                                <span class="sr-only">Emitir cobrança</span>
                                            </button>
                                        @endunless

                                        {{-- Reenviar o aviso: o e-mail original se perde na caixa
                                             de entrada, e ligar para pedir que procurem custa
                                             mais do que mandar de novo. --}}
                                        <button type="submit" class="acao-linha" title="Reenviar a cobrança por e-mail"
                                                form="reenviar-{{ $fatura->id }}">
                                            <x-avalia.icone nome="envelope" />
                                            <span class="sr-only">Reenviar por e-mail</span>
                                        </button>
                                    @endunless

                                    @if ($fatura->estaLiquidada())
                                        {{-- Pagamento desfeito acontece: chargeback, Pix devolvido,
                                             boleto baixado por engano. Sem este caminho a correcao
                                             so existiria no banco. --}}
                                        <div x-data="{ aberto: false }" class="inline-block text-left">
                                            <button type="button" class="acao-linha" x-show="! aberto"
                                                    x-on:click="aberto = true" title="Desfazer o recebimento">
                                                <x-avalia.icone nome="desfazer" />
                                                <span class="sr-only">Desfazer o recebimento</span>
                                            </button>

                                            <div x-show="aberto" x-cloak class="flex items-center gap-2">
                                                <label for="estorno-{{ $fatura->id }}" class="sr-only">
                                                    Por que o recebimento foi desfeito
                                                </label>
                                                <input id="estorno-{{ $fatura->id }}" name="motivo" type="text"
                                                       form="estornar-{{ $fatura->id }}"
                                                       class="campo-linha w-64" required minlength="10" maxlength="255"
                                                       placeholder="Por que o recebimento foi desfeito">

                                                <x-avalia.botao tamanho="sm" form="estornar-{{ $fatura->id }}">Desfazer</x-avalia.botao>
                                                <x-avalia.botao variante="secundario" tamanho="sm"
                                                                type="button" x-on:click="aberto = false">
                                                    Cancelar
                                                </x-avalia.botao>
                                            </div>
                                        </div>
                                    @else
                                        {{-- A justificativa e obrigatoria porque esta e a unica
                                             porta pela qual dinheiro e dado como recebido sem ter
                                             entrado, e ela libera a comissao na mesma hora. --}}
                                        <div x-data="{ aberto: false }" class="inline-block text-left">
                                            <button type="button" class="acao-linha acao-linha-ok" x-show="! aberto"
                                                    x-on:click="aberto = true" title="Confirmar pagamento recebido">
                                                <x-avalia.icone nome="confirmar" />
                                                <span class="sr-only">Confirmar pagamento recebido</span>
                                            </button>

                                            <div x-show="aberto" x-cloak class="flex items-center gap-2">
                                                <label for="motivo-{{ $fatura->id }}" class="sr-only">
                                                    Como o pagamento foi conferido
                                                </label>
                                                <input id="motivo-{{ $fatura->id }}" name="motivo" type="text"
                                                       form="liquidar-{{ $fatura->id }}"
                                                       class="campo-linha w-64" required minlength="10" maxlength="255"
                                                       placeholder="Como o pagamento foi conferido">

                                                <x-avalia.botao tamanho="sm" form="liquidar-{{ $fatura->id }}">Confirmar</x-avalia.botao>
                                                <x-avalia.botao variante="secundario" tamanho="sm"
                                                                type="button" x-on:click="aberto = false">
                                                    Cancelar
                                                </x-avalia.botao>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="tabela-vazia">Nenhuma fatura corresponde a este filtro. Ajuste a busca para continuar.</td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- O que o RECORTE soma, e não a operação inteira. Sem esta
                     linha o operador filtra, olha os cartões do topo e conclui
                     que o filtro não pegou, porque aqueles números continuam os
                     mesmos de propósito. --}}
                @if ($resumo['quantidade'] > 0)
                    <tfoot class="border-t border-gray-100 dark:border-gray-800">
                        <tr>
                            <td colspan="4" class="px-5 py-4 text-left text-sm text-gray-500 dark:text-gray-400">
                                {{ $resumo['quantidade'] }}
                                {{ $resumo['quantidade'] === 1 ? 'fatura no recorte' : 'faturas no recorte' }},
                                {{ Dinheiro::brl($resumo['aberto_cents']) }} em aberto e
                                {{ Dinheiro::brl($resumo['liquidado_cents']) }} já recebidos.
                            </td>
                            <td class="px-5 py-4 text-right font-semibold tabular-nums whitespace-nowrap text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($resumo['total_cents']) }}
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        </div>
    </form>

    {{-- Os formulários de linha ficam FORA do formulário do lote.

         Estavam dentro, e form dentro de form o navegador descarta: os botões de
         emitir cobrança, reenviar, confirmar e desfazer pagamento passavam a
         enviar o formulário do lote, que responde por outra rota. Nenhum teste
         pegou porque a suíte posta direto nas rotas, sem passar pelo HTML.

         Cada botão se liga ao seu pelo atributo `form`, e os campos de
         justificativa também: sem o atributo no campo, o motivo não iria junto e
         a baixa seria recusada por falta dele. --}}
    @foreach ($faturas as $fatura)
        @unless ($fatura->estaLiquidada())
            @unless ($fatura->cobrancaAsaas?->asaas_charge_id)
                <form method="POST" action="{{ route('financeiro.cobranca', $fatura) }}" id="cobranca-{{ $fatura->id }}">
                    @csrf
                </form>
            @endunless

            <form method="POST" action="{{ route('financeiro.reenviar', $fatura) }}" id="reenviar-{{ $fatura->id }}">
                @csrf
            </form>
        @endunless

        @if ($fatura->estaLiquidada())
            <form method="POST" action="{{ route('financeiro.estornar', $fatura) }}" id="estornar-{{ $fatura->id }}">
                @csrf
            </form>
        @else
            <form method="POST" action="{{ route('financeiro.liquidar', $fatura) }}" id="liquidar-{{ $fatura->id }}">
                @csrf
            </form>
        @endif
    @endforeach

    @if ($comissoes->isNotEmpty())
        <div class="cartao mt-6 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="font-medium text-gray-800 dark:text-white/90">Comissão a repassar</h2>
                <p class="ajuda-campo mt-1">Valores já devidos, apurados sobre faturas com pagamento confirmado.</p>
            </div>

            <div class="overflow-x-auto">
            <table class="tabela min-w-[28rem]">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($comissoes as $linha)
                        <tr>
                            <td class="px-6 py-4 text-left text-gray-800 dark:text-white/90">
                                {{ $linha->vendedor->nome }}
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $linha->faturas }} fatura(s)
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium tabular-nums text-gray-800 dark:text-white/90">
                                {{ Dinheiro::brl($linha->total_cents) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
@endsection
