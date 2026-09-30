<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quatro placas vendidas em 28/09/2026 ficaram com `custo_cents` igual a ZERO.
 *
 * Nenhuma placa custa zero, e o efeito nao era cosmetico: com custo zero o
 * liquido virava o preco cheio, e a comissao de 25% saia sobre R$ 89,90 em vez
 * de sobre R$ 84,40. A placa 7BT9XE comissionou R$ 22,48 onde deveria ser
 * R$ 21,10.
 *
 * Como aconteceu: a migration de 28/09 gravou o custo das vendas antigas a
 * partir de `config('etiquetas.custo_cents')`, e naquele deploy a chave ainda nao
 * existia, entao `(int) null` deu 0. A migration seguinte, de 29/09, corrigiu
 * apenas as linhas em que o custo era exatamente 500, o valor anterior, e nao
 * viu as que estavam em 0.
 *
 * Isto NAO fere a regra de congelar custo na emissao. Zero nunca foi custo
 * praticado: e ausencia de dado gravada como numero, e o proprio valor que o
 * congelamento existe para proteger nunca chegou a ser escrito. O numero usado e
 * o mesmo e unico que a casa conhece, como ja fez `recuperarCustos()`.
 *
 * Achado pela simulacao do lastro do razao (`avalia:lastrear-plaquinhas
 * --simular`), antes de o erro entrar na contabilidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        $custo = (int) config('etiquetas.custo_cents');

        // Guarda contra o proprio defeito que corrige: se a chave sumir do
        // config outra vez, e melhor nao fazer nada do que gravar zero de novo.
        if ($custo <= 0) {
            return;
        }

        DB::table('etiquetas')
            ->whereNotNull('vendida_em')
            ->where('custo_cents', 0)
            ->update(['custo_cents' => $custo]);
    }

    /**
     * Sem volta.
     *
     * Voltar seria regravar zero, que e o defeito.
     */
    public function down(): void {}
};
