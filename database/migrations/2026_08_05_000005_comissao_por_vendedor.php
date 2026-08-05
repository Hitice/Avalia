<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentual de comissao por vendedor.
 *
 * A aliquota deixa de ser a mesma para todo mundo: a administracao negocia caso
 * a caso e precisa poder pagar diferente sem tocar em codigo. O padrao continua
 * sendo 10%, que e o parametro comercial da secao 5 do PDD.
 *
 * O percentual usado ja e congelado na fatura desde o primeiro dia, entao mudar
 * a taxa de um vendedor nao reescreve competencia fechada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->unsignedTinyInteger('comissao_pct')->default(10)->after('papel');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->dropColumn('comissao_pct');
        });
    }
};
