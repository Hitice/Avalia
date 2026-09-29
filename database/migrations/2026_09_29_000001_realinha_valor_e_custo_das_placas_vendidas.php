<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Poe as placas ja registradas no preco e no custo que valem hoje.
 *
 * Isto CONTRARIA a regra da casa de que valor gravado na venda nao se reescreve,
 * e por isso precisa do motivo escrito: nenhuma destas placas foi vendida. Elas
 * entraram em campo durante a montagem do produto, e nasceram com o preco que o
 * config tinha na hora (79,90) e com o custo que se supunha (5,00). Nao ha
 * cliente que pagou esses numeros nem comissao calculada sobre eles.
 *
 * O que a regra protege e cobranca feita: fatura emitida, repasse pago,
 * conferencia fechada. Nada disso existe aqui, entao corrigir dado de montagem
 * nao e reescrever historia, e deixar 79,90 seria mostrar no painel um
 * faturamento que nunca foi cobrado.
 *
 * Daqui em diante a regra volta a valer inteira: `VenderEtiqueta` congela preco
 * e custo na venda, e reajuste de config so alcanca venda nova. Se um dia
 * precisar mudar valor de venda de verdade, o caminho e a tela, com trilha de
 * auditoria, e nao outra migration como esta.
 *
 * Roda uma vez. Placa cujo valor ja e o de hoje nao e tocada, entao repetir a
 * publicacao nao gera escrita nem confunde quem le o log.
 */
return new class extends Migration
{
    /** O que valia na montagem, e que so existe nas placas nao vendidas. */
    private const VALOR_ANTIGO = 7_990;

    private const CUSTO_ANTIGO = 500;

    public function up(): void
    {
        $valor = (int) config('etiquetas.precos.placa_cents');
        $custo = (int) config('etiquetas.custo_cents');

        // Restrito ao par antigo de proposito. Sem isso, um valor negociado a
        // mao (placa mais cara, cortesia, desconto) seria apagado junto, e o
        // acerto de quem digitou o numero certo viraria o numero padrao.
        DB::table('etiquetas')
            ->whereNotNull('vendida_em')
            ->where('valor_cents', self::VALOR_ANTIGO)
            ->update(['valor_cents' => $valor]);

        DB::table('etiquetas')
            ->whereNotNull('vendida_em')
            ->where('custo_cents', self::CUSTO_ANTIGO)
            ->update(['custo_cents' => $custo]);
    }

    /**
     * Sem volta.
     *
     * Reverter devolveria 79,90 a placas que podem ter sido vendidas de verdade
     * depois desta publicacao, e ai sim seria reescrever cobranca feita. O
     * caminho de conserto e a tela.
     */
    public function down(): void {}
};
