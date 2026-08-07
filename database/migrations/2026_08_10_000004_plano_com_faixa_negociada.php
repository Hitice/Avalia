<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A faixa de preco negociada, separada do consumo minimo.
 *
 * E a bonificacao que fecha venda: o cliente assume minimo de R$ 500 e leva a
 * tabela de precos da faixa de R$ 1.000. Nulo significa o comportamento de
 * sempre: a tabela da propria faixa do minimo. Com a faixa separada, o minimo
 * vira valor livre (R$ 1.350 se o comercial quiser), porque quem precisa
 * existir no catalogo e a faixa de PRECO, nao o piso de cobranca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $t) {
            $t->unsignedBigInteger('faixa_preco_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $t) {
            $t->dropColumn('faixa_preco_cents');
        });
    }
};
