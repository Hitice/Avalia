<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sai a coluna `ativo` dos leads.
 *
 * Ela era o marcador "(INATIVO)" que a base de origem trazia colado no nome, e
 * quem responde por isso agora e `situacao`, o estagio do funil. A informacao
 * nao se perde: o unico lead que vinha marcado ficou como "nao atender" e
 * carrega o porque na observacao, gravados pela migration anterior.
 *
 * Vem numa versao SEPARADA da que parou de usar a coluna, e nao junto, porque o
 * retorno do deploy volta o codigo e nao o banco: publicadas na mesma versao, um
 * retorno deixaria o codigo antigo lendo uma coluna que nao existe mais, e o que
 * era um deploy com problema viraria site fora do ar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            // O indice sai antes, e explicito: o MySQL derruba junto com a
            // coluna, mas o SQLite da suite reconstroi a tabela e o nome
            // pendurado sobra.
            $t->dropIndex(['ativo']);
            $t->dropColumn('ativo');
        });
    }

    /**
     * Recria a coluna e a deduz do funil, que e a inversa fiel do que a
     * migration anterior fez. O valor original nao existe mais em lugar
     * nenhum, e inventar um seria pior que deduzir.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->boolean('ativo')->default(true)->index()->after('origem');
        });

        DB::table('leads')->where('situacao', 'bloqueado')->update(['ativo' => false]);
    }
};
