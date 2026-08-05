<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margem liquida que a Avalia quer em cada consulta.
 *
 * Em pontos-base, como o imposto: 3000 = 30,00%. E margem DEPOIS de pagar
 * fornecedor, fisco e comissao do vendedor, que foi a decisao comercial.
 *
 * Guarda o alvo no catalogo, e nao em config, pelo mesmo motivo do imposto:
 * muda com decisao comercial e precisa de rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->unsignedInteger('margem_alvo_bps')->default(3000);
        });
    }

    public function down(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->dropColumn('margem_alvo_bps');
        });
    }
};
