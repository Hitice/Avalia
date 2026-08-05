@extends('layouts.app', ['title' => $servico->exists ? $servico->nome : 'Novo servico'])

@php
    use App\Support\Dinheiro;
    use App\Support\Margem;

    $primeiroCusto = ($precos ?? collect())->first()?->custo_cents;

    $rotulo = 'rotulo-campo';
    $campo = 'campo';
    $erro = 'erro-campo';
@endphp

@section('content')
    <a href="{{ route('catalogo.servicos.index') }}"
       class="hover:text-brand-500 dark:hover:text-brand-400 mb-2 inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Servicos
    </a>

    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">
        {{ $servico->exists ? $servico->nome : 'Novo servico' }}
    </h1>

    @include('paginas.catalogo.avisos')

    <div class="cartao p-6">
        <form method="POST"
              action="{{ $servico->exists ? route('catalogo.servicos.atualizar', $servico) : route('catalogo.servicos.salvar') }}">
            @csrf
            @if ($servico->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nome" class="{{ $rotulo }}">Nome comercial</label>
                    <input id="nome" name="nome" type="text" class="{{ $campo }}"
                           value="{{ old('nome', $servico->nome) }}" required>
                    <span class="ajuda-campo">
                        E o que o cliente ve. Nao use marca nem nomenclatura do fornecedor.
                    </span>
                    @error('nome') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="codigo" class="{{ $rotulo }}">Codigo</label>
                    @if ($servico->exists)
                        <input id="codigo" type="text" class="{{ $campo }} cursor-not-allowed opacity-60"
                               value="{{ $servico->codigo }}" disabled>
                        <span class="ajuda-campo">
                            Nao muda depois de criado: e por ele que franquia, relatorio e integracao
                            acham o servico.
                        </span>
                    @else
                        <input id="codigo" name="codigo" type="text" class="{{ $campo }}"
                               value="{{ old('codigo') }}" placeholder="score-positivo" required>
                        <span class="ajuda-campo">
                            Minusculas, numeros e hifen. Escolha com calma: e definitivo.
                        </span>
                    @endif
                    @error('codigo') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="categoria" class="{{ $rotulo }}">Categoria</label>
                    <select id="categoria" name="categoria" class="{{ $campo }}" required>
                        @foreach (\App\Enums\Categoria::rotulos() as $chave => $nome)
                            <option value="{{ $chave }}" @selected(old('categoria', $servico->categoria) === $chave)>
                                {{ $nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('categoria') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                @unless ($servico->exists)
                    <div class="sm:col-span-2">
                        <label for="preco_base" class="{{ $rotulo }}">Preco inicial</label>
                        <input id="preco_base" name="preco_base" type="text" inputmode="decimal"
                               class="{{ $campo }}" value="{{ old('preco_base') }}" placeholder="6,31" required>
                        <span class="ajuda-campo">
                            Entra igual em {{ count($faixas) }} faixa(s) do catalogo. Servico sem preco nao
                            apareceria na matriz, que e o unico lugar onde se edita preco. Ajuste faixa a
                            faixa depois de criar.
                        </span>
                        @error('preco_base_cents') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                    </div>
                @endunless

                <div class="flex items-center">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="ativo" value="0">
                        <input type="checkbox" name="ativo" value="1" class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('ativo', $servico->ativo ?? true))>
                        Servico ativo
                    </label>
                </div>

                <div class="flex items-center">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="exige_liberacao" value="0">
                        <input type="checkbox" name="exige_liberacao" value="1" class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('exige_liberacao', $servico->exige_liberacao ?? false))>
                        Aguarda liberacao juridica
                    </label>
                </div>

                <p class="text-xs text-gray-500 sm:col-span-2 dark:text-gray-400">
                    Servico que aguarda liberacao aparece no catalogo e pode ser precificado, mas nao
                    entra em plano nenhum e nenhuma consulta sai para o fornecedor. E o estado dos
                    servicos de SCR ate a homologacao juridica e contratual.
                </p>
            </div>

            @if ($servico->exists && $faixas !== [])
                <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-800">
                    <h2 class="mb-1 font-medium text-gray-800 dark:text-white/90">Custo e preco</h2>
                    <p class="ajuda-campo mb-5">
                        O custo vale para todas as faixas: o fornecedor cobra por consulta, nao pelo
                        pacote do cliente. Campo em branco significa custo ainda nao cadastrado.
                    </p>

                    <div class="max-w-xs">
                        <label for="custo" class="rotulo-campo">Custo do fornecedor</label>
                        <input id="custo" name="custo" type="text" inputmode="decimal" placeholder="-"
                               value="{{ old('custo', $primeiroCusto === null ? '' : Dinheiro::numero($primeiroCusto)) }}"
                               class="campo">
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="tabela">
                            <thead class="tabela-cabecalho">
                                <tr>
                                    <th class="tabela-th text-left">Faixa</th>
                                    <th class="px-4 py-3 text-right font-medium">Preco de venda</th>
                                    <th class="px-4 py-3 text-right font-medium">Margem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($faixas as $faixa)
                                    @php
                                        $preco = $precos->get($faixa);
                                        $pct = $preco
                                            ? Margem::pct($preco->preco_cents, $preco->custo_cents,
                                                $catalogo->imposto_bps, $catalogo->comissaoBps())
                                            : null;
                                    @endphp

                                    <tr>
                                        <td class="tabela-td text-left text-gray-600 dark:text-gray-300">
                                            {{ Dinheiro::faixa($faixa) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <input type="text" inputmode="decimal"
                                                   name="precos[{{ $faixa }}]"
                                                   value="{{ old('precos.'.$faixa, $preco ? Dinheiro::numero($preco->preco_cents) : '') }}"
                                                   class="campo-celula">
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                            {{ $pct === null ? '-' : number_format($pct, 1, ',', '.').'%' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="mt-6 flex gap-3">
                <x-avalia.botao>
                    {{ $servico->exists ? 'Salvar' : 'Criar servico' }}
                </x-avalia.botao>
                <x-avalia.botao variante="secundario" :href="route('catalogo.servicos.index')">Cancelar</x-avalia.botao>
            </div>
        </form>
    </div>
@endsection
