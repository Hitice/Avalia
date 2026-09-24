@php
    use App\Enums\SituacaoEtiqueta;
    use App\Support\Dinheiro;

    $estado = $etiqueta->estado();
@endphp

@extends('layouts.app', ['title' => 'Plaquinha '.$etiqueta->codigo])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-mono text-2xl font-semibold tracking-widest text-gray-800 dark:text-white/90">
                {{ $etiqueta->codigo }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $etiqueta->titulo ?? 'Sem apelido' }} ·
                {{ $tipos[$etiqueta->tipo] ?? $etiqueta->tipo }} ·
                {{ $etiqueta->lote ? 'Tiragem '.$etiqueta->lote->codigo.', nº '.$etiqueta->sequencia : 'Avulso' }}
            </p>
        </div>

        <a href="{{ route('etiquetas.index') }}" class="botao botao-secundario">Voltar</a>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-5">
            <form method="POST" action="{{ route('etiquetas.apontar', $etiqueta) }}" class="cartao grid gap-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <h2 class="rotulo-grupo">Para onde esta plaquinha leva</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        O endereço impresso não muda nunca. Só este campo muda, e vale em até um minuto.
                    </p>
                </div>

                <div>
                    <label for="destino" class="rotulo-campo">Destino</label>
                    <input id="destino" name="destino" type="text" required class="campo"
                           value="{{ old('destino', $etiqueta->destino) }}"
                           placeholder="https://wa.me/5531999999999">
                    <p class="ajuda-campo">WhatsApp, Instagram, avaliação no Google, cardápio, o site do cliente.</p>
                    @error('destino')<p class="erro-campo">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="titulo" class="rotulo-campo">Apelido interno</label>
                        <input id="titulo" name="titulo" type="text" maxlength="120" class="campo"
                               value="{{ old('titulo', $etiqueta->titulo) }}">
                    </div>

                    <div>
                        <label for="cliente_nome" class="rotulo-campo">Cliente</label>
                        <input id="cliente_nome" name="cliente_nome" type="text" maxlength="150" class="campo"
                               value="{{ old('cliente_nome', $etiqueta->cliente_nome) }}">
                    </div>

                    <div>
                        <label for="cliente_contato" class="rotulo-campo">Contato</label>
                        <input id="cliente_contato" name="cliente_contato" type="text" maxlength="150" class="campo"
                               value="{{ old('cliente_contato', $etiqueta->cliente_contato) }}">
                    </div>

                    @if ($etiqueta->vendida_em === null)
                        <div>
                            <label for="valor" class="rotulo-campo">Valor cobrado</label>
                            <input id="valor" name="valor" type="text" class="campo"
                                   value="{{ old('valor', Dinheiro::numero((int) config('etiquetas.precos.placa_cents'))) }}">
                            {{-- Gravado na venda, e nao lido da tabela depois:
                                 reajuste de hoje nao reescreve o que foi
                                 cobrado ontem. --}}
                            <p class="ajuda-campo">Fica gravado nesta plaquinha, e não muda quando a tabela mudar.</p>
                        </div>
                    @endif
                </div>

                <div>
                    <x-avalia.botao>
                        {{ $etiqueta->vendida_em === null ? 'Vender e pôr no ar' : 'Salvar destino' }}
                    </x-avalia.botao>
                </div>
            </form>

            @if ($etiqueta->destinos->isNotEmpty())
                <div class="cartao overflow-hidden">
                    <h2 class="rotulo-grupo px-6 pt-6">Para onde já apontou</h2>

                    <div class="tabela-rolagem mt-4 max-h-80">
                        <table class="tabela">
                            <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                                <tr>
                                    <th scope="col" class="tabela-th text-left">Destino</th>
                                    <th scope="col" class="tabela-th text-left">De</th>
                                    <th scope="col" class="tabela-th text-left">Até</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($etiqueta->destinos as $historico)
                                    <tr>
                                        <td class="tabela-td max-w-[24rem] truncate">{{ $historico->destino }}</td>
                                        <td class="tabela-td">{{ $historico->vigorou_de->format('d/m/Y') }}</td>
                                        <td class="tabela-td">
                                            {{ $historico->vigorou_ate?->format('d/m/Y') ?? 'agora' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="cartao p-6">
                <h2 class="rotulo-grupo">Situação</h2>

                <p class="mt-4">
                    <span @class([
                        'etiqueta',
                        'etiqueta-sucesso' => $estado === 'ativa',
                        'etiqueta-alerta' => in_array($estado, ['carencia', 'em_branco'], true),
                        'etiqueta-erro' => in_array($estado, ['vencida', 'suspensa'], true),
                        'etiqueta-neutra' => $estado === 'baixada',
                    ])>
                        @switch($estado)
                            @case('carencia') Em carência @break
                            @case('vencida') Vencida @break
                            @default {{ $etiqueta->situacao->rotulo() }}
                        @endswitch
                    </span>
                </p>

                <dl class="mt-5 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Endereço impresso</dt>
                        <dd class="font-mono text-xs text-gray-700 dark:text-gray-300">/q/{{ $etiqueta->codigo }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Vendida em</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $etiqueta->vendida_em?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Vence em</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $etiqueta->vence_em?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Leituras</dt>
                        <dd class="text-gray-700 tabular-nums dark:text-gray-300">{{ $etiqueta->total_acessos }}</dd>
                    </div>
                </dl>

                @if ($estado === 'carencia')
                    <p class="aviso aviso-alerta mt-5">
                        Venceu e continua no ar pela carência, até
                        {{ $etiqueta->fimDaCarencia()->format('d/m/Y') }}.
                    </p>
                @endif

                <div class="mt-6 flex flex-wrap gap-2">
                    @if (in_array($etiqueta->situacao, [SituacaoEtiqueta::Ativa, SituacaoEtiqueta::Suspensa], true))
                        <form method="POST" action="{{ route('etiquetas.alternar', $etiqueta) }}">
                            @csrf
                            <x-avalia.botao variante="secundario" tamanho="sm">
                                {{ $etiqueta->situacao === SituacaoEtiqueta::Ativa ? 'Suspender' : 'Reativar' }}
                            </x-avalia.botao>
                        </form>
                    @endif

                    @if ($etiqueta->vendida_em)
                        <form method="POST" action="{{ route('etiquetas.renovar', $etiqueta) }}">
                            @csrf
                            <x-avalia.botao variante="secundario" tamanho="sm">
                                Renovar {{ Dinheiro::brl((int) config('etiquetas.precos.renovacao_cents')) }}
                            </x-avalia.botao>
                        </form>
                    @endif
                </div>
            </div>

            @if ($acessos->isNotEmpty())
                <div class="cartao p-6">
                    <h2 class="rotulo-grupo">Leituras por dia</h2>

                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($acessos as $dia)
                            <li class="flex justify-between gap-4">
                                <span class="text-gray-500 dark:text-gray-400">{{ $dia->dia->format('d/m/Y') }}</span>
                                <span class="text-gray-700 tabular-nums dark:text-gray-300">{{ $dia->total }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($etiqueta->situacao !== SituacaoEtiqueta::Baixada)
                <form method="POST" action="{{ route('etiquetas.baixar', $etiqueta) }}" class="cartao grid gap-4 p-6">
                    @csrf

                    <div>
                        <h2 class="rotulo-grupo">Baixar de vez</h2>
                        {{-- Nao e exclusao, pela regra da casa. E o codigo nao
                             volta ao bolo: reciclado, mandaria a freguesia do
                             cliente antigo para a loja de um estranho. --}}
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Placa quebrada, cliente que saiu. Não volta a circular, e o código
                            fica reservado para sempre.
                        </p>
                    </div>

                    <div>
                        <label for="motivo" class="rotulo-campo">Motivo</label>
                        <input id="motivo" name="motivo" type="text" maxlength="150" class="campo">
                    </div>

                    <div>
                        <x-avalia.botao variante="secundario" tamanho="sm">Baixar plaquinha</x-avalia.botao>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
