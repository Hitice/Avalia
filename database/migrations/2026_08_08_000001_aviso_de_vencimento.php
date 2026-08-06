<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carimbo do lembrete de vencimento na fatura.
 *
 * E o que torna a rotina de aviso idempotente: sem ele, cada rodada dentro da
 * janela mandaria o mesmo lembrete de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->timestamp('aviso_vencimento_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn('aviso_vencimento_em');
        });
    }
};
