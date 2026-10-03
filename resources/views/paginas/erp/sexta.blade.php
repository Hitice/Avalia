@extends('layouts.app', ['title' => 'Sexta-feira'])

@php use App\Support\Dinheiro; @endphp

@section('content')
    <x-avalia.cabecalho-pagina titulo="Sexta-feira" subtitulo="Pagamentos da semana e lista de Pix" />

    @include('parciais.avisos')

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="cartao p-6">
            <h2 class="titulo-cartao">Comissão de placas</h2>
            @forelse ($sales as $r)
                <form method="POST" action="{{ route('erp.sexta.sales', $r['id']) }}" class="mt-4 flex items-center justify-between gap-3 text-sm">
                    @csrf
                    <span class="text-gray-800 dark:text-white/90">{{ $r['nome'] }} <span class="ajuda-campo">{{ $r['placas'] }} {{ $r['placas'] === 1 ? 'placa' : 'placas' }}</span></span>
                    <x-avalia.botao tamanho="sm" onclick="return confirm('Marcar {{ Dinheiro::brl($r['cents']) }} como pagos a {{ $r['nome'] }}?')">Pagar {{ Dinheiro::brl($r['cents']) }}</x-avalia.botao>
                </form>
            @empty
                <p class="subtitulo-pagina">Nenhuma comissão em aberto.</p>
            @endforelse
        </div>

        <div class="cartao p-6">
            <h2 class="titulo-cartao">Comissão de consultas</h2>
            @forelse ($one as $r)
                <form method="POST" action="{{ route('erp.sexta.one', $r['id']) }}" class="mt-4 flex items-center justify-between gap-3 text-sm">
                    @csrf
                    <span class="text-gray-800 dark:text-white/90">
                        {{ $r['nome'] }}
                        <span class="ajuda-campo">{{ $r['faturas'] }} {{ $r['faturas'] === 1 ? 'fatura' : 'faturas' }} · liberado {{ Dinheiro::brl($r['liberado']) }}@if ($r['demonstracoes']) · demonstrações {{ Dinheiro::brl($r['demonstracoes']) }}@endif</span>
                    </span>
                    @if ($r['cents'] > 0)
                        <x-avalia.botao tamanho="sm" onclick="return confirm('Marcar {{ Dinheiro::brl($r['cents']) }} como pagos a {{ $r['nome'] }}?')">Pagar {{ Dinheiro::brl($r['cents']) }}</x-avalia.botao>
                    @else
                        <span class="etiqueta etiqueta-neutra">demonstrações cobrem</span>
                    @endif
                </form>
            @empty
                <p class="subtitulo-pagina">Nenhuma comissão em aberto.</p>
            @endforelse
        </div>

        <div class="cartao p-6">
            <h2 class="titulo-cartao">Pró-labore de {{ now()->translatedFormat('F') }}</h2>
            @foreach ($prolabore as $r)
                <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-800 dark:text-white/90">
                        {{ $r['staff']->nome }}
                        <span class="ajuda-campo">parte {{ Dinheiro::brl($r['parte']) }} · pró-labore {{ Dinheiro::brl($r['prolabore']) }} · já lançado {{ Dinheiro::brl($r['lancado']) }}</span>
                    </span>
                    @if ($r['socio'] === null)
                        <span class="etiqueta etiqueta-alerta" title="Cadastre o sócio em Sócios, ligado a esta conta">sem cadastro de sócio</span>
                    @elseif ($r['sugerido'] > 0)
                        <form method="POST" action="{{ route('erp.sexta.prolabore', $r['socio']) }}" class="flex items-center gap-2">
                            @csrf
                            <input name="valor" type="text" inputmode="decimal" class="campo w-28 text-right" value="{{ Dinheiro::numero($r['sugerido']) }}">
                            <x-avalia.botao tamanho="sm">Lançar</x-avalia.botao>
                        </form>
                    @else
                        <span class="etiqueta etiqueta-sucesso">em dia</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="cartao p-6">
            <h2 class="titulo-cartao">Contas que vencem até {{ now()->endOfWeek()->format('d/m') }}</h2>
            @forelse ($contas as $conta)
                <form method="POST" action="{{ route('erp.sexta.conta', $conta) }}" class="mt-4 flex items-center justify-between gap-3 text-sm">
                    @csrf
                    <span class="text-gray-800 dark:text-white/90">
                        {{ $conta->descricao }}
                        <span class="ajuda-campo">{{ $conta->fornecedor }}{{ $conta->fornecedor ? ' · ' : '' }}vence {{ $conta->vence_em->format('d/m') }}</span>
                    </span>
                    <x-avalia.botao tamanho="sm" variante="secundario">Pagar {{ Dinheiro::brl($conta->valor_cents) }}</x-avalia.botao>
                </form>
            @empty
                <p class="subtitulo-pagina">Nenhuma conta vencendo nesta semana.</p>
            @endforelse
        </div>
    </div>

    {{-- A lista para fazer os Pix no app do banco, na ordem. --}}
    <div class="mt-6 cartao overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="titulo-cartao">Lista de Pix</h2>
        </div>
        <table class="tabela">
            <thead class="tabela-cabecalho"><tr>
                <th scope="col" class="tabela-th text-left">Quem</th>
                <th scope="col" class="tabela-th text-left">Chave</th>
                <th scope="col" class="tabela-th text-left">Motivo</th>
                <th scope="col" class="tabela-th text-right">Valor</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($pix as $p)
                    <tr>
                        <td class="tabela-td text-gray-800 dark:text-white/90">{{ $p['nome'] }}</td>
                        <td class="tabela-td font-mono text-gray-600 dark:text-gray-300">{{ $p['chave'] ?: 'sem chave cadastrada' }}</td>
                        <td class="tabela-td text-gray-600 dark:text-gray-300">{{ $p['motivo'] }}</td>
                        <td class="tabela-td text-right tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($p['cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="tabela-vazia">Nada a pagar nesta sexta.</td></tr>
                @endforelse
                @if ($pix->isNotEmpty())
                    <tr class="border-t-2 border-gray-200 dark:border-gray-700">
                        <td colspan="3" class="tabela-td font-medium text-gray-800 dark:text-white/90">Total</td>
                        <td class="tabela-td text-right font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ Dinheiro::brl($pix->sum('cents')) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
@endsection
