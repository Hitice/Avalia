{{-- Uma celula da matriz na visao de margem. Recebe $preco e $catalogo.

     Mostra a porcentagem, que e comparavel entre servicos de preco muito
     diferente; os valores em reais vao no title, para nao poluir 7 colunas. --}}

@php
    use App\Support\Dinheiro;
    use App\Support\Margem;

    $imposto = $catalogo->imposto_bps;
    $liquida = Margem::liquidaCents($preco->preco_cents, $preco->custo_cents, $imposto);
    $pct = Margem::pct($preco->preco_cents, $preco->custo_cents, $imposto);
    $piso = Margem::pisoCents($preco->custo_cents, $imposto);
@endphp

@if ($liquida === null)
    <span class="text-gray-400 dark:text-gray-600" title="Custo do fornecedor ainda nao cadastrado">—</span>
@else
    <span class="{{ $liquida < 0 ? 'text-error-600 dark:text-error-400 font-medium' : 'text-gray-700 dark:text-gray-200' }}"
          title="Venda {{ Dinheiro::brl($preco->preco_cents) }} · Custo {{ Dinheiro::brl($preco->custo_cents) }} · Imposto {{ Dinheiro::brl(Margem::impostoCents($preco->preco_cents, $imposto)) }} · Sobra {{ Dinheiro::brl($liquida) }} · Piso {{ Dinheiro::brl($piso) }}">
        {{ number_format($pct, 1, ',', '.') }}%
    </span>
@endif
