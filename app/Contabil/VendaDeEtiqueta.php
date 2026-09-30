<?php

namespace App\Contabil;

use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Support\RepartePlaquinha;

/**
 * Uma venda de plaquinha, traduzida em pernas do razao.
 *
 * O reparte sai de `RepartePlaquinha`: nascer aqui de novo daria dois lugares
 * com direito de dizer quanto o Warley ganhou.
 *
 * Custo entra na VENDA, e nao na compra do lote, porque nao existe registro de
 * compra. Quando existir, a contrapartida passa a ser estoque.
 */
final class VendaDeEtiqueta
{
    public function __construct(private readonly Lancar $lancar) {}

    /**
     * @param  list<int>  $sociosIds  contas da equipe que sao socios, e por isso nao comissionam
     */
    public function partidas(Etiqueta $venda, array $sociosIds): Partidas
    {
        $parte = self::reparte($venda, $sociosIds);

        return Partidas::porCodigo([
            // Sem a comissao: ela e passivo ate ser paga, e descontar do caixa
            // agora contaria o mesmo dinheiro como divida e como saida.
            'caixa' => $parte['bruto'] - $parte['custo'],
            'receita:plaquinha' => -$parte['bruto'],
            'custo:plaquinha' => $parte['custo'],

            // Despesa do mes e divida em aberto na mesma hora.
            'comissao' => $parte['comissao'],
            'comissao-a-pagar' => -$parte['comissao'],
        ]);
    }

    /** Lanca a venda, ou nao faz nada se ela ja foi lancada. */
    public function registrar(Etiqueta $venda, array $sociosIds): ?LancamentoFinanceiro
    {
        $vendida = $venda->vendida_em;

        return $this->lancar->umaVez($this->partidas($venda, $sociosIds), [
            'natureza' => 'receita',
            'descricao' => 'Venda da plaquinha '.$venda->codigo,
            'competencia' => $vendida->format('Y-m'),
            'ocorrido_em' => $vendida->toDateString(),
            'origem_tipo' => 'etiqueta',
            'origem_id' => $venda->id,

            // O lastro roda por comando, sem sessao. Quem vendeu fica no
            // documento, e nao no autor: autor e quem lancou.
            'documento' => $venda->vendedor_id === null ? null : 'vendedor:'.$venda->vendedor_id,
        ]);
    }

    /**
     * O reparte de uma venda, com a mesma regra que o painel aplica.
     *
     * @param  list<int>  $sociosIds
     * @return array{bruto: int, custo: int, liquido: int, comissao: int, lucro: int}
     */
    public static function reparte(Etiqueta $venda, array $sociosIds): array
    {
        $vendedorId = $venda->vendedor_id === null ? null : (int) $venda->vendedor_id;

        // Placa sem vendedor nao comissiona, igual a de socio: nao houve venda de
        // ninguem. Comissionar venda orfa cria dinheiro sem destinatario, e foi
        // assim que o total da tela passou a soma das linhas por vendedor.
        $geraComissao = $vendedorId !== null && ! in_array($vendedorId, $sociosIds, true);

        return RepartePlaquinha::de(
            (int) $venda->valor_cents,

            // Venda anterior a coluna de custo cai no config. Zero mostraria
            // lucro inflado, que e o erro que engana.
            $venda->custo_cents === null ? (int) config('etiquetas.custo_cents') : (int) $venda->custo_cents,

            $geraComissao,
            (int) config('etiquetas.comissao_pct'),
        );
    }
}
