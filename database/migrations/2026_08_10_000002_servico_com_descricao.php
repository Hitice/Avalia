<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que a consulta devolve, dito para quem compra.
 *
 * Muita gente contrata sem saber o que cada consulta traz. A descricao aparece
 * no formulario de consulta do cliente, junto do preco, antes do clique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->string('descricao', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->dropColumn('descricao');
        });
    }
};
