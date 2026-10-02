@php
    use App\Support\Dinheiro;
@endphp

@extends('layouts.app', ['title' => 'Códigos'])

@section('content')
    <x-avalia.cabecalho-pagina titulo="QR Code dinâmico" subtitulo="Gere, baixe e cadastre a URL no futuro." />
    @include('parciais.avisos')

    {{-- So o cadastro aqui. Gerar codigos tem pagina propria, de
         administracao: e producao, e nao venda. --}}
    <div class="mb-5">
        <form method="POST" action="{{ route('etiquetas.apontar-codigo') }}" class="cartao grid gap-4 p-6">
            @csrf

            <div>
                <h2 class="rotulo-grupo">Área de cadastro</h2>
            </div>

            {{-- O codigo e curto e o nome e longo: lado a lado eles ocupam a
                 mesma linha que o codigo ocupava sozinho, e o cartao nao cresce. --}}
            <div class="flex flex-wrap items-start gap-3">
                <div class="w-36">
                    <label for="codigo" class="rotulo-campo">Código impresso</label>
                    <input id="codigo" name="codigo" type="text" maxlength="20" required
                           value="{{ old('codigo') }}"
                           class="campo font-mono tracking-widest uppercase" placeholder="K7M2PX">
                    @error('codigo')<p class="erro-campo">{{ $message }}</p>@enderror
                </div>

                <div class="min-w-[12rem] flex-1">
                    <label for="cliente_nome" class="rotulo-campo">Cliente</label>
                    <input id="cliente_nome" name="cliente_nome" type="text" maxlength="150"
                           value="{{ old('cliente_nome') }}" class="campo" placeholder="Padaria do Zé">
                </div>
            </div>

            {{-- O botao ao lado do campo, e nao embaixo: os dois formam uma
                 acao so, e quem acabou de digitar a URL ja tem o cursor ali. --}}
            <div>
                <label for="destino-rapido" class="rotulo-campo">Para onde leva</label>

                <div class="flex flex-wrap items-center gap-3">
                    <input id="destino-rapido" name="destino" type="text" required
                           class="campo min-w-[12rem] flex-1"
                           value="{{ old('destino') }}" placeholder="https://wa.me/5531999999999">

                    <x-avalia.botao class="shrink-0">Cadastrar destino</x-avalia.botao>
                </div>

                @error('destino')<p class="erro-campo">{{ $message }}</p>@enderror
            </div>
        </form>
    </div>



    <div class="cartao overflow-hidden"
         @if ($campanha && $pacote->isNotEmpty())
             x-data="tiragem(@js(['pasta' => $campanha->pasta(), 'etiquetas' => $pacote, 'pastaLocal' => config('etiquetas.pasta_local')]))"
         @endif>
        {{-- Os filtros moram DENTRO da tabela, e o pacote da campanha sai do
             proprio seletor: escolher a campanha e baixar o ZIP dela sao a
             mesma tarefa, e separa-las em dois cartoes fazia o operador
             procurar em dois lugares o que e um gesto so. --}}
        <form method="GET" class="barra-secao"
              x-data="{
                  vez: 0,
                  async buscar() {
                      const vez = ++this.vez;
                      const url = new URL(window.location.href);
                      url.search = new URLSearchParams(new FormData(this.$el)).toString();
                      history.replaceState(null, '', url);
                      url.searchParams.set('parcial', '1');
                      const resposta = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                      if (resposta.ok && vez === this.vez) {
                          document.getElementById('tabela-etiquetas').outerHTML = await resposta.text();
                      }
                  },
              }">
            <div class="min-w-[14rem] flex-1">
                <label for="busca" class="rotulo-campo">Buscar</label>
                {{-- Busca no servidor a cada tecla, com debounce: a tabela pagina em
                     25, e filtrar so a pagina na tela esconderia resultado das outras.
                     Troca so a tabela; recarregar a pagina tirava o foco do campo. --}}
                <input id="busca" name="busca" type="search" value="{{ $filtros['busca'] }}" class="campo"
                       placeholder="Empresa, contato, telefone, código ou destino"
                       x-on:input.debounce.300ms="buscar()">
            </div>

            <div>
                <label for="situacao" class="rotulo-campo">Situação</label>
                <select id="situacao" name="situacao" class="campo" x-on:change="buscar()">
                    <option value="">Todas</option>
                    @foreach ($situacoes as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="lote" class="rotulo-campo">Campanha</label>
                <select id="lote" name="lote" class="campo" x-on:change="buscar()">
                    <option value="">Todas</option>
                    @foreach ($lotes as $lote)
                        <option value="{{ $lote->id }}" @selected((string) $filtros['lote'] === (string) $lote->id)>
                            {{ $lote->titulo }} ({{ $lote->quantidade }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="vendedor" class="rotulo-campo">Vendido por</label>
                <select id="vendedor" name="vendedor" class="campo" x-on:change="buscar()">
                    <option value="">Todos</option>
                    @foreach ($vendedores as $pessoa)
                        <option value="{{ $pessoa->id }}" @selected((string) $filtros['vendedor'] === (string) $pessoa->id)>
                            {{ $pessoa->nome }}
                        </option>
                    @endforeach
                    <option value="sem" @selected($filtros['vendedor'] === 'sem')>Não identificado</option>
                </select>
            </div>

            <x-avalia.botao variante="secundario">Filtrar</x-avalia.botao>
        </form>

        @if ($campanha && $pacote->isNotEmpty())
            {{-- Barra propria: filtrar e exportar sao tarefas diferentes, e na
                 mesma linha os controles de uma empurravam os da outra. --}}
            <div class="barra-secao">
                <div>
                    <label for="formato" class="rotulo-campo">Formato</label>
                    <select id="formato" x-model="formato" class="campo w-auto py-2">
                        <option value="svg">SVG</option>
                        <option value="png">PNG</option>
                        <option value="ambos">SVG e PNG</option>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <x-avalia.botao x-on:click.prevent="baixar()" x-bind:disabled="gerando">
                        <span x-show="! gerando">Baixar {{ $pacote->count() }} em ZIP</span>
                        <span x-show="gerando" x-cloak>Montando…</span>
                    </x-avalia.botao>

                    <span x-show="gerando" x-cloak class="text-sm text-gray-500 tabular-nums dark:text-gray-400">
                        <span x-text="feito"></span> de {{ $pacote->count() }}
                    </span>
                </div>

                <p x-show="erro" x-cloak x-text="erro" class="aviso aviso-erro w-full"></p>

                {{-- Fora da vista: o componente so precisa de onde desenhar. --}}
                <div x-ref="prova" class="hidden"></div>
            </div>
        @endif

        @include('paginas.etiquetas._tabela')
    </div>
@endsection
