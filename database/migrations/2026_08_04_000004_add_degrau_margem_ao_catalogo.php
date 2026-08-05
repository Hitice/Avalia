<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quanto de margem cada degrau abaixo do topo ganha.
 *
 * A margem alvo passa a valer para a MAIOR faixa, que e o piso comercial. Cada
 * faixa abaixo dela soma este degrau, entao o preco unitario cai conforme o
 * cliente sobe de pacote.
 *
 * E o que faz o pacote maior valer a pena sem furar o piso: com custo fixo do
 * fornecedor, desconto por volume so cabe se a faixa de baixo render mais.
 * Zero aqui achata a escada e deixa todas as faixas no mesmo preco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->unsignedInteger('degrau_margem_bps')->default(300);
        });
    }

    public function down(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->dropColumn('degrau_margem_bps');
        });
    }
};
