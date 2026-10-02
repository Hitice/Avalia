@extends('layouts.app', ['title' => 'Controladoria'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Controladoria</h1>
            <p class="rotulo-grupo mt-1">O resultado da casa, somado do razão</p>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <label for="competencia" class="sr-only">Competência</label>
            <select id="competencia" name="competencia" class="campo w-auto py-2" onchange="this.form.submit()">
                @foreach ($competencias as $mes)
                    <option value="{{ $mes }}" @selected($mes === $competencia)>{{ $mes }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-avalia.cartao-indicador rotulo="Caixa" :valor="Dinheiro::brl($caixa)"
                                   tom="text-brand-600 dark:text-brand-400"
                                   ajuda="Saldo de sempre, e não do mês" />
        <x-avalia.cartao-indicador rotulo="Lucro dos produtos no mês" :valor="Dinheiro::brl($lucro)" />
        <x-avalia.cartao-indicador rotulo="A receber de clientes" :valor="Dinheiro::brl($aReceber)" />
        <x-avalia.cartao-indicador rotulo="Comissão a pagar" :valor="Dinheiro::brl($aPagarDeComissao)" />
    </div>

    <div class="cartao mb-6 p-5">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Resultado por produto</h2>

        <div class="tabela-rolagem">
            <table class="tabela">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="tabela-th">Produto</th>
                        <th class="tabela-th text-right">Receita</th>
                        <th class="tabela-th text-right">Custo</th>
                        <th class="tabela-th text-right">Comissão</th>
                        <th class="tabela-th text-right">Lucro</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($produtos as $produto)
                        <tr>
                            <td class="tabela-td">
                                {{ $produto['nome'] }}
                                @unless ($produto['lancado'])
                                    <span class="etiqueta etiqueta-neutra ml-2">não lança no razão ainda</span>
                                @endunless
                            </td>
                            <td class="tabela-td text-right">{{ Dinheiro::brl($produto['receita']) }}</td>
                            <td class="tabela-td text-right">{{ Dinheiro::brl($produto['custo']) }}</td>
                            <td class="tabela-td text-right">{{ Dinheiro::brl($produto['comissao']) }}</td>
                            <td class="tabela-td text-right">{{ Dinheiro::brl($produto['lucro']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="tabela-cabecalho">
                    <tr>
                        <th class="tabela-th">Total</th>
                        <th class="tabela-th text-right">{{ Dinheiro::brl(collect($produtos)->sum('receita')) }}</th>
                        <th class="tabela-th text-right">{{ Dinheiro::brl(collect($produtos)->sum('custo')) }}</th>
                        <th class="tabela-th text-right">{{ Dinheiro::brl(collect($produtos)->sum('comissao')) }}</th>
                        <th class="tabela-th text-right">{{ Dinheiro::brl($lucro) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="ajuda-campo mt-3">
            Despesa da casa no mês: {{ Dinheiro::brl($despesa) }}. Ela não entra no lucro por produto,
            que é antes de custo fixo.
        </p>
    </div>

    <div class="cartao p-5">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Sócios</h2>

        @if ($porSocio->isEmpty())
            <p class="tabela-vazia">Nenhum sócio cadastrado. O cadastro fica em Sócios.</p>
        @else
            <div class="tabela-rolagem">
                <table class="tabela">
                    <thead class="tabela-cabecalho">
                        <tr>
                            <th class="tabela-th">Sócio</th>
                            <th class="tabela-th text-right">Participação</th>
                            <th class="tabela-th text-right">Cabe a ele no mês</th>
                            <th class="tabela-th text-right">Aportou</th>
                            <th class="tabela-th text-right">A devolver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porSocio as $socio)
                            <tr>
                                <td class="tabela-td">{{ $socio['nome'] }}</td>
                                <td class="tabela-td text-right">{{ number_format($socio['participacao'] / 100, 2, ',', '.') }}%</td>
                                <td class="tabela-td text-right">{{ Dinheiro::brl($socio['cabe']) }}</td>
                                <td class="tabela-td text-right">{{ Dinheiro::brl($socio['aportou']) }}</td>
                                <td class="tabela-td text-right">{{ Dinheiro::brl($socio['a_devolver']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($participacaoTotal !== 10000)
                <div class="aviso aviso-alerta mt-4">
                    As participações somam {{ number_format($participacaoTotal / 100, 2, ',', '.') }}%, e não 100%.
                    A coluna "cabe a ele" está errada enquanto isso.
                </div>
            @endif

            <p class="ajuda-campo mt-3">
                "Cabe a ele" é a parte no lucro pela participação, e não o que há para retirar:
                distribuição depende de decisão, e entra como lançamento em Sócios.
            </p>
        @endif
    </div>
@endsection
