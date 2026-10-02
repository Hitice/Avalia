<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quando a comissao desta venda foi paga ao vendedor.
 *
 * A comissao atual do vendedor e a soma das vendas com isto em branco. Marcar o
 * pagamento venda a venda, e nao com um contador por vendedor, deixa cada
 * centavo explicavel: a tela diz quais placas entraram em cada pagamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiquetas', function (Blueprint $t) {
            $t->timestamp('comissao_paga_em')->nullable()->after('vendida_em');
        });
    }

    public function down(): void
    {
        Schema::table('etiquetas', function (Blueprint $t) {
            $t->dropColumn('comissao_paga_em');
        });
    }
};
