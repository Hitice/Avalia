@php
    use App\Enums\SituacaoEtiqueta;
    use App\Support\Dinheiro;

    $estado = $etiqueta->estado();
@endphp

@extends('layouts.ferramenta', ['title' => 'Código '.$etiqueta->codigo])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-mono text-2xl font-semibold tracking-widest text-gray-800 dark:text-white/90">
                {{ $etiqueta->codigo }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $etiqueta->titulo ?? $etiqueta->lote?->titulo ?? 'Sem campanha' }}
                @if ($etiqueta->sequencia)
                    · nº {{ $etiqueta->sequencia }}
                @endif
            </p>
        </div>

        <a href="{{ route('etiquetas.index') }}" class="botao botao-secundario">Voltar</a>
    </div>

    @include('paginas.catalogo.avisos')

    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-5">
            @if ($etiqueta->situacao === SituacaoEtiqueta::Baixada)
                {{-- Botao que so serve para receber recusa e botao quebrado aos
                     olhos de quem clica. Codigo encerrado nao volta, entao a
                     tela diz isso em vez de oferecer o formulario. --}}
                <div class="cartao p-6">
                    <h2 class="rotulo-grupo">Código encerrado</h2>
                    <p class="mt-3 leading-relaxed text-gray-600 dark:text-gray-300">
                        Este código foi encerrado e não volta a circular. O código fica reservado para
                        sempre: reciclado, ele mandaria a freguesia do cliente antigo, que ainda tem
                        a placa velha em algum lugar, para a loja de um estranho.
                    </p>
                    @if ($etiqueta->destino)
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            Apontava para <span class="text-gray-700 dark:text-gray-300">{{ $etiqueta->destino }}</span>.
                        </p>
                    @endif
                </div>
            @else
            <form method="POST" action="{{ route('etiquetas.apontar', $etiqueta) }}" class="cartao grid gap-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <h2 class="rotulo-grupo">Para onde este código leva</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        O código impresso é permanente. Alterações de destino entram em até um minuto.
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

                <div>
                    <label for="titulo" class="rotulo-campo">Campanha</label>
                    <input id="titulo" name="titulo" type="text" maxlength="120" class="campo"
                           value="{{ old('titulo', $etiqueta->titulo) }}"
                           placeholder="ex: Campanha Floripa 2026">
                </div>

                <div>
                    <x-avalia.botao>Salvar destino</x-avalia.botao>
                </div>
            </form>
            @endif

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
            {{-- O codigo, desenhado aqui no navegador. Existe
                 desde que a etiqueta nasce, mesmo em branco: o negocio inteiro
                 depende de imprimir primeiro e vender depois. --}}
            <div class="cartao p-6"
                 x-data="gerador(@js(['conteudo' => $etiqueta->urlParaQr(), 'mm' => 30, 'logo' => true, 'nome' => $etiqueta->nomeDeArquivo()]))">
                <h2 class="rotulo-grupo">O código</h2>

                <div class="mt-4 flex justify-center rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white">
                    <div x-ref="prova"></div>
                </div>

                <p class="mt-3 text-center font-mono text-xs break-all text-gray-500 dark:text-gray-400">
                    {{ $etiqueta->url() }}
                </p>


                <label class="mt-5 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="logo" class="size-4 rounded border-gray-300">
                    Marca da Avalia no miolo
                </label>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <x-avalia.botao tamanho="sm" x-on:click="baixarSvg()">Baixar SVG</x-avalia.botao>
                    <button type="button" x-on:click="baixarPng()" class="botao botao-secundario botao-sm">
                        Baixar PNG
                    </button>
                </div>
            </div>

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

        </div>
    </div>
@endsection
