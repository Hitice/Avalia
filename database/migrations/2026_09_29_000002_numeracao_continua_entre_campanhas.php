<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A numeracao das plaquinhas passa a correr entre campanhas.
 *
 * Antes cada tiragem comecava do 1, entao existiam varias "placa 3" e o numero
 * so identificava alguma coisa junto com o nome do lote. Para conferir producao
 * contra a grafica isso nao serve: quem esta na bancada diz "a 143", e nao "a 3
 * da campanha Floripa".
 *
 * Renumera o que ja existe, em vez de so valer daqui para a frente. Numeracao
 * continua que comeca no meio nao e continua, e o controle que ela existe para
 * dar nao apareceria para as campanhas antigas.
 *
 * A ordem e a de producao: campanha por campanha, e dentro dela a sequencia que
 * a tiragem tinha. Ninguem e reordenado, so deslocado.
 *
 * Os avulsos ficam de fora. Plaquinha sem lote nasce com `sequencia` nula, e
 * nula ela continua: ela nao veio de tiragem e nao entra na contagem da
 * producao.
 *
 * ATENCAO ao que isto muda fora do banco: o nome do arquivo dentro do ZIP sai
 * da sequencia. Um pacote baixado antes desta migration tem nome diferente do
 * mesmo pacote baixado depois. O codigo impresso na placa nao muda, e e ele que
 * identifica a plaquinha em campo, entao nada que ja esta na rua e afetado.
 */
return new class extends Migration
{
    /**
     * Deslocamento temporario para a primeira passada.
     *
     * A unicidade e por (lote_id, sequencia), e renumerar linha a linha pode
     * fazer um numero novo colidir com um antigo que ainda nao foi atualizado
     * no mesmo lote. Tirar todo mundo para uma faixa que ninguem ocupa resolve
     * sem depender da ordem em que o banco aplica as linhas.
     */
    private const LONGE = 1_000_000;

    public function up(): void
    {
        $comLote = DB::table('etiquetas')
            ->whereNotNull('lote_id')
            ->whereNotNull('sequencia')
            ->orderBy('lote_id')
            ->orderBy('sequencia')
            ->orderBy('id')
            ->pluck('id');

        if ($comLote->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($comLote) {
            DB::table('etiquetas')->whereIn('id', $comLote)->update([
                'sequencia' => DB::raw('sequencia + '.self::LONGE),
            ]);

            foreach ($comLote as $posicao => $id) {
                DB::table('etiquetas')->where('id', $id)->update(['sequencia' => $posicao + 1]);
            }
        });
    }

    /**
     * Sem volta.
     *
     * Reverter exigiria saber onde cada campanha comecava, e essa informacao
     * deixa de existir no instante em que a renumeracao roda. O caminho de
     * conserto, se precisar, e renumerar de novo pela mesma ordem.
     */
    public function down(): void {}
};
