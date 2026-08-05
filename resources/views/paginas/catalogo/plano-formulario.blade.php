@extends('layouts.app', ['title' => $plano->exists ? $plano->nome : 'Novo plano'])

@php
    use App\Support\Dinheiro;

    // Faixas do catalogo. O valor vai no formato que o operador digitaria,
    // porque e o mesmo campo que aceita digitacao livre no servidor.
    $opcoesFaixa = collect($faixas)->map(fn ($centavos) => [
        'valor' => Dinheiro::numero($centavos),
        'rotulo' => Dinheiro::faixa($centavos),
    ])->values();

    $rotulo = 'rotulo-campo';
    $campo = 'campo';
    $erro = 'erro-campo';
@endphp

@section('content')
    <a href="{{ route('catalogo.index') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Planos
    </a>

    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">
        {{ $plano->exists ? $plano->nome : 'Novo plano' }}
    </h1>

    @include('paginas.catalogo.avisos')

    <div class="cartao p-6">
        <form method="POST"
              action="{{ $plano->exists ? route('catalogo.planos.atualizar', $plano) : route('catalogo.planos.salvar') }}"
              x-data="{
                  faixas: {{ Js::from($opcoesFaixa) }},
                  faixa: '{{ old('consumo_minimo', Dinheiro::numero($plano->consumo_minimo_cents ?? 0)) }}',
              }">
            @csrf
            @if ($plano->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nome" class="{{ $rotulo }}">Nome</label>
                    <input id="nome" name="nome" type="text" class="{{ $campo }}"
                           value="{{ old('nome', $plano->nome) }}" required>
                    @error('nome') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="descricao" class="{{ $rotulo }}">Descricao</label>
                    <textarea id="descricao" name="descricao" rows="2" class="{{ $campo }}">{{ old('descricao', $plano->descricao) }}</textarea>
                    @error('descricao') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="consumo_minimo" class="{{ $rotulo }}">Consumo minimo</label>
                    <select id="consumo_minimo" name="consumo_minimo" class="{{ $campo }}" x-model="faixa" required>
                        <template x-for="f in faixas" :key="f.valor">
                            <option :value="f.valor" x-text="f.rotulo"></option>
                        </template>
                    </select>
                    <span class="ajuda-campo">
                        Define a coluna de precos e a aliquota de comissao.
                    </span>
                    @error('consumo_minimo') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="mensalidade" class="{{ $rotulo }}">Mensalidade</label>
                    <input id="mensalidade" name="mensalidade" type="text" inputmode="decimal" class="{{ $campo }}"
                           value="{{ old('mensalidade', Dinheiro::numero($plano->mensalidade_cents ?? 0)) }}" required>
                    <span class="ajuda-campo">
                        Cobrada sempre, consumindo ou nao. Nao entra na faixa de comissao.
                    </span>
                    @error('mensalidade_cents') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-end">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="ativo" value="0">
                        <input type="checkbox" name="ativo" value="1" class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('ativo', $plano->ativo ?? true))>
                        Plano ativo
                    </label>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <x-avalia.botao>
                    {{ $plano->exists ? 'Salvar' : 'Criar plano' }}
                </x-avalia.botao>
                <x-avalia.botao variante="secundario" :href="route('catalogo.index')">Cancelar</x-avalia.botao>
            </div>
        </form>
    </div>

    @if ($plano->exists)
        <div class="mt-6 cartao p-6">
            <h2 class="font-medium text-gray-800 dark:text-white/90">Franquia por servico</h2>
            <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">
                Quantas consultas de cada servico ja vem pagas na mensalidade. Zero significa
                que o servico esta liberado, mas toda consulta e excedente.
            </p>

            @if ($servicos->isEmpty())
                <div class="aviso aviso-alerta">
                    Nenhum servico precificado na faixa deste plano. Confira a versao e o consumo minimo.
                </div>
            @else
                <form method="POST" action="{{ route('catalogo.planos.franquias', $plano) }}">
                    @csrf
                    @method('PUT')

                    <div class="overflow-x-auto">
                        <table class="tabela min-w-[34rem]">
                            <thead class="tabela-cabecalho">
                                <tr>
                                    <th class="py-3 pr-4 text-left font-medium">Servico</th>
                                    <th class="px-4 py-3 text-right font-medium">Preco na faixa</th>
                                    <th class="py-3 pl-4 text-right font-medium">Incluidas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($servicos as $servico)
                                    <tr>
                                        <td class="py-2.5 pr-4">
                                            <span class="block text-gray-800 dark:text-white/90">{{ $servico->nome }}</span>
                                            <code class="text-xs text-gray-500 dark:text-gray-400">{{ $servico->codigo }}</code>
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            {{ isset($precos[$servico->id]) ? Dinheiro::brl($precos[$servico->id]) : '-' }}
                                        </td>
                                        <td class="py-2.5 pl-4 text-right">
                                            <input type="number" min="0" max="1000000"
                                                   name="franquias[{{ $servico->id }}]"
                                                   value="{{ old('franquias.'.$servico->id, $franquias[$servico->id] ?? 0) }}"
                                                   class="w-28 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-right text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="bg-brand-500 hover:bg-brand-600 mt-5 rounded-lg px-4 py-2.5 text-sm font-medium text-white">
                        Salvar franquia
                    </button>
                </form>
            @endif
        </div>
    @endif
@endsection
