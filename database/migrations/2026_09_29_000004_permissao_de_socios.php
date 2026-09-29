<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem enxerga as financas da sociedade.
 *
 * Permissao propria, e nao `admin` nem `pode_financeiro`. As tres respondem
 * perguntas diferentes: administrar e operar o produto, financeiro e confirmar
 * pagamento de cliente, e isto aqui e o caixa dos donos, com aporte, retirada e
 * quanto a empresa deve a cada um.
 *
 * Nasce negada para todo mundo, inclusive para quem ja e admin, pela mesma
 * razao de `pode_financeiro`: permissao que vem ligada por heranca e permissao
 * que ninguem decidiu conceder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('staff', 'pode_socios')) {
            return;
        }

        Schema::table('staff', function (Blueprint $t) {
            $t->boolean('pode_socios')->default(false)->after('pode_financeiro');
        });
    }

    public function down(): void
    {
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn('pode_socios'));
    }
};
