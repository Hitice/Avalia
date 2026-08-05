{{-- Uma celula da matriz na visao de margem. Recebe $preco e $catalogo.

     Mostra a porcentagem, que e comparavel entre servicos de preco muito
     diferente; os valores em reais vao no title, para nao poluir 7 colunas. --}}

@php
    use App\Support\Dinheiro;
    use App\Support\Margem;

    $imposto = $catalogo->imposto_bps;
    $comissao = $catalogo->comissaoBps();
    $liquida = Margem::liquidaCents($preco->preco_cents, $preco->custo_cents, $imposto, $comissao);
    $pct = Margem::pct($preco->preco_cents, $preco->custo_cents, $imposto, $comissao);
    $piso = Margem::pisoCents($preco->custo_cents, $imposto, $comissao);
    $alvo = Margem::precoAlvoCents($preco->custo_cents, $imposto, $comissao, $catalogo->margem_alvo_bps);
    $abaixoDoAlvo = ! Margem::atinge($preco->preco_cents, $preco->custo_cents, $imposto, $comissao, $catalogo->margem_alvo_bps);
@endphp

@if ($liquida === null)
    <span class="text-gray-400 dark:text-gray-600" title="Custo do fornecedor ainda nao cadastrado">-</span>
@else
    <span class="{{ $liquida < 0 ? 'text-error-600 dark:text-error-400 font-medium' : ($abaixoDoAlvo ? 'text-warning-600 dark:text-warning-400' : 'text-gray-700 dark:text-gray-200') }}"
          title="Venda {{ Dinheiro::brl($preco->preco_cents) }} · Custo {{ Dinheiro::brl($preco->custo_cents) }} · Imposto {{ Dinheiro::brl(Margem::impostoCents($preco->preco_cents, $imposto)) }} · Comissao {{ Dinheiro::brl(Margem::comissaoCents($preco->preco_cents, $comissao)) }} · Sobra {{ Dinheiro::brl($liquida) }} · Piso {{ Dinheiro::brl($piso) }} · Alvo {{ Dinheiro::brl($alvo) }}">
        {{ number_format($pct, 1, ',', '.') }}%
    </span>
@endif
