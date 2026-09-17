<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cobrancas_asaas.cliente_id` passa a aceitar nulo.
 *
 * A tabela nasceu para a fatura da Avalia One, em que o pagador e sempre uma
 * empresa contratante nossa. No Avalia 360 quem paga e o cliente final de um
 * produtor: ele nao tem cadastro aqui, e nao deve ter. Quem compra de um
 * produtor nao vira base da Avalia One.
 *
 * O vinculo que importa no 360 esta do outro lado: `parcelas_360` aponta para
 * a cobranca, e e por ali que o webhook acha o que dar baixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas_asaas', function (Blueprint $t) {
            $t->foreignId('cliente_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas_asaas', function (Blueprint $t) {
            $t->foreignId('cliente_id')->nullable(false)->change();
        });
    }
};
