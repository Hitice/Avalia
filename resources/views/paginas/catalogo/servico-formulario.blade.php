@extends('layouts.app', ['title' => $servico->exists ? $servico->nome : 'Novo serviço'])

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
        Serviços
    </a>

    <h1 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white/90">
        {{ $servico->exists ? $servico->nome : 'Novo serviço' }}
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
                        É o que o cliente vê. Não use marca nem nomenclatura do fornecedor.
                    </span>
                    @error('nome') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="descricao" class="{{ $rotulo }}">O que a consulta devolve</label>
                    <textarea id="descricao" name="descricao" class="{{ $campo }}" rows="2"
                              maxlength="300">{{ old('descricao', $servico->descricao) }}</textarea>
                    <span class="ajuda-campo">
                        Aparece para o cliente no formulário de consulta, junto do preço. Uma frase
                        do que vem no resultado, sem citar fornecedor.
                    </span>
                    @error('descricao') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="codigo" class="{{ $rotulo }}">Código</label>
                    @if ($servico->exists)
                        <input id="codigo" type="text" class="{{ $campo }} cursor-not-allowed opacity-60"
                               value="{{ $servico->codigo }}" disabled>
                        <span class="ajuda-campo">
                            Não muda depois de criado: é por ele que franquia, relatório e catálogo
                            identificam o serviço.
                        </span>
                    @else
                        <input id="codigo" name="codigo" type="text" class="{{ $campo }}"
                               value="{{ old('codigo') }}" placeholder="score-positivo" required>
                        <span class="ajuda-campo">
                            Minúsculas, números e hífen. Escolha com calma: é definitivo.
                        </span>
                    @endif
                    @error('codigo') <span class="{{ $erro }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="codigo_fornecedor" class="{{ $rotulo }}">Relatório no fornecedor</label>
                    <input id="codigo_fornecedor" name="codigo_fornecedor" type="text" class="{{ $campo }}"
                           value="{{ old('codigo_fornecedor', $servico->codigo_fornecedor) }}"
                           placeholder="RELATORIO_AVANCADO_PF">
                    <span class="ajuda-campo">
                        O nome do produto no contrato do bureau. Sem ele, a consulta real
                        não sai e a tela explica o motivo. Quando o serviço atende PF e PJ e
                        o fornecedor tem um produto para cada, escreva os dois separados por
                        barra, na ordem <code>PF|PJ</code>: por exemplo
                        <code>SCPC_NET_PF|SCPC_NET_PJ</code>. O sistema escolhe pelo documento
                        consultado.
                    </span>
                </label>

                <label class="block">
                    <span class="rotulo-campo">Fornecedor</span>
                    <select name="fornecedor" class="campo">
                        <option value="">Usar o fornecedor padrão do sistema</option>
                        @foreach (App\Support\Fornecedores::bureaus() as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected(old('fornecedor', $servico->fornecedor) === $chave)>
                                {{ $rotulo }}
                            </option>
                        @endforeach
                    </select>
                    <span class="ajuda-campo">
                        De qual bureau esta linha vem. O catálogo mistura bases, e sem isto
                        ligar um fornecedor mandaria para ele até o que ele não vende.
                        Em branco, vale a escolha geral do sistema.
                    </span>
                    @error('codigo_fornecedor') <span class="{{ $erro }}">{{ $message }}</span> @enderror
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
                        <label for="preco_base" class="{{ $rotulo }}">Preço inicial</label>
                        <input id="preco_base" name="preco_base" type="text" inputmode="decimal"
                               class="{{ $campo }}" value="{{ old('preco_base') }}" placeholder="6,31" required>
                        <span class="ajuda-campo">
                            Entra igual em {{ count($faixas) }} faixa(s) do catálogo. Serviço sem preço não
                            apareceria na matriz, que é o único lugar onde se edita preço. Ajuste faixa a
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
                        Serviço ativo
                    </label>
                </div>

                <div class="flex items-center">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="exige_liberacao" value="0">
                        <input type="checkbox" name="exige_liberacao" value="1" class="size-4 rounded border-gray-300 dark:border-gray-700"
                               @checked(old('exige_liberacao', $servico->exige_liberacao ?? false))>
                        Aguarda liberação jurídica
                    </label>
                </div>

                <p class="text-xs text-gray-500 sm:col-span-2 dark:text-gray-400">
                    Serviço que aguarda liberação aparece no catálogo e pode ser precificado, mas não
                    entra em plano nenhum e nenhuma consulta sai para o fornecedor. E o estado dos
                    serviços de SCR até a homologação jurídica e contratual.
                </p>
            </div>

            @if ($servico->exists && $faixas !== [])
                <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-800">
                    <h2 class="mb-1 font-medium text-gray-800 dark:text-white/90">Custo e preço</h2>
                    <p class="ajuda-campo mb-5">
                        O custo vale para todas as faixas: o fornecedor cobra por consulta, não pelo
                        pacote do cliente. Campo em branco significa custo ainda não cadastrado.
                    </p>

                    @if ($servico->suprimido())
                        {{-- Os campos continuam editaveis: e aqui que a estimativa se mantem.
                             O que nao sai daqui e o numero para o resto do sistema. --}}
                        <div class="aviso aviso-alerta mb-5">
                            Serviço veicular. Preço, custo e margem são estimativa até o contrato com o
                            fornecedor: eles aparecem nesta página, mas não no catálogo nem em proposta.
                        </div>
                    @endif

                    <div class="max-w-xs">
                        <label for="custo" class="rotulo-campo">Custo Fornecedor</label>
                        <input id="custo" name="custo" type="text" inputmode="decimal" placeholder="-"
                               value="{{ old('custo', $primeiroCusto === null ? '' : Dinheiro::numero($primeiroCusto)) }}"
                               class="campo">
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="tabela">
                            <thead class="tabela-cabecalho">
                                <tr>
                                    <th class="tabela-th text-left">Faixa</th>
                                    <th class="px-4 py-3 text-right font-medium">Preço de venda</th>
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
