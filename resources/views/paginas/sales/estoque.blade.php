@extends('layouts.app', ['title' => 'Estoque'])

@section('content')
    <x-avalia.cabecalho-pagina titulo="Estoque" rotulo="Placas disponíveis" />
    @include('parciais.avisos')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @if ($ehAdmin)
            <x-avalia.cartao-indicador rotulo="Placas disponíveis" :valor="$disponiveis" tom="text-brand-600 dark:text-brand-400" ajuda="Geradas e ainda não vendidas" />
            <x-avalia.cartao-indicador rotulo="Geradas" :valor="$geradas" :href="route('etiquetas.criar')" />
            <x-avalia.cartao-indicador rotulo="Sem dono" :valor="$noBolo" ajuda="Prontas para entregar" />
        @else
            <x-avalia.cartao-indicador rotulo="Placas disponíveis" :valor="$minhas->count()" tom="text-brand-600 dark:text-brand-400" />
        @endif
    </div>

    @if ($podePlacas)
        <div class="cartao mb-6 p-5">
            <h2 class="titulo-secao">Entregar placas</h2>

            <form method="POST" action="{{ route('sales.estoque.entregar') }}"
                  class="flex flex-wrap items-end gap-3">
                @csrf

                <div class="min-w-[14rem]">
                    <label for="vendedor_id" class="rotulo-campo">Para quem</label>
                    <select id="vendedor_id" name="vendedor_id" class="campo" required>
                        @foreach ($equipe as $pessoa)
                            <option value="{{ $pessoa->id }}">{{ $pessoa->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="quantas" class="rotulo-campo">Quantas</label>
                    <input id="quantas" name="quantas" type="number" min="1" max="500" value="20"
                           class="campo w-28" required>
                </div>

                <x-avalia.botao>Entregar</x-avalia.botao>
            </form>

            <p class="ajuda-campo mt-3">Saem as de menor número primeiro.</p>
        </div>

        {{-- Entregar placas ESCOLHIDAS: o admin le os codigos impressos nas que
             separou e digita. Tudo ou nada, e a recusa nomeia o codigo errado. --}}
        <div class="cartao mb-6 p-5">
            <h2 class="titulo-secao">Entregar por código</h2>

            <form method="POST" action="{{ route('sales.estoque.entregar-codigos') }}" class="grid gap-3">
                @csrf

                <div class="grid gap-3 sm:grid-cols-[14rem_1fr]">
                    <div>
                        <label for="vendedor_codigos" class="rotulo-campo">Para quem</label>
                        <select id="vendedor_codigos" name="vendedor_id" class="campo" required>
                            @foreach ($equipe as $pessoa)
                                <option value="{{ $pessoa->id }}" @selected(old('vendedor_id') == $pessoa->id)>{{ $pessoa->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="codigos" class="rotulo-campo">Códigos, separados por vírgula</label>
                        <input id="codigos" name="codigos" type="text" class="campo font-mono" required maxlength="2000"
                                  placeholder="4VRBD6, FSNSE3, 1R94CH" value="{{ old('codigos') }}">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <x-avalia.botao>Entregar</x-avalia.botao>
                    <span class="ajuda-campo">Até 20 placas por vez. Código errado recusa o lote.</span>
                </div>
            </form>
        </div>

        <div class="cartao mb-6 overflow-hidden">
            <h2 class="p-5 pb-0 text-lg font-semibold text-gray-800 dark:text-white/90">Estoque da equipe</h2>

            <div class="tabela-rolagem mt-4">
                <table class="tabela">
                    <thead class="tabela-cabecalho">
                        <tr>
                            <th class="tabela-th text-left">Vendedor</th>
                            <th class="tabela-th text-right">Na mão</th>
                            <th class="tabela-th text-right">Já vendeu</th>
                            <th class="tabela-th text-right">Devolver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($porVendedor as $pessoa)
                            <tr>
                                <td class="tabela-td">{{ $pessoa->nome }}</td>
                                <td class="tabela-td text-right tabular-nums">{{ $pessoa->em_maos }}</td>
                                <td class="tabela-td text-right tabular-nums">{{ $pessoa->vendidas }}</td>
                                <td class="tabela-td text-right">
                                    @if ($pessoa->em_maos > 0)
                                        <form method="POST" action="{{ route('sales.estoque.devolver', $pessoa) }}">
                                            @csrf
                                            <x-avalia.botao variante="secundario" class="botao-sm">
                                                Devolver {{ $pessoa->em_maos }}
                                            </x-avalia.botao>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="tabela-vazia">
                                    Ninguém pegou placa ainda. Use o formulário acima.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <h2 class="p-5 pb-0 text-lg font-semibold text-gray-800 dark:text-white/90">
            Placas em estoque
        </h2>

        <div class="tabela-rolagem mt-4">
            <table class="tabela">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="tabela-th text-left">Número</th>
                        <th class="tabela-th text-left">Código</th>
                        <th class="tabela-th text-left">Desde</th>
                        <th class="tabela-th text-right">Apontar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($minhas as $placa)
                        <tr>
                            <td class="tabela-td tabular-nums">{{ $placa->sequencia ?? '—' }}</td>
                            <td class="tabela-td font-mono">{{ $placa->codigo }}</td>
                            <td class="tabela-td">{{ $placa->consignada_em?->format('d/m/Y') ?? '—' }}</td>
                            <td class="tabela-td text-right">
                                <a href="{{ route('etiquetas.ficha', $placa) }}"
                                   class="botao botao-secundario botao-sm">Apontar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tabela-vazia">
                                Você não tem placa em mão. Fale com a administração.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
