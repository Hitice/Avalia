<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aliquota de imposto sobre a venda, para calcular margem.
 *
 * Em pontos-base (bps) e nao em porcentagem com virgula: 2700 = 27,00%. Mesma
 * disciplina do dinheiro em centavos: inteiro do banco ate a tela, sem float
 * no meio do caminho de um calculo que decide preco.
 *
 * Fica no catalogo e nao em config porque muda com regime tributario e precisa
 * de rastro: quem mudou e quando aparece no updated_at da linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            // 27% e a estimativa registrada no PDD, secao 5, ainda a confirmar
            // com a contabilidade.
            $t->unsignedInteger('imposto_bps')->default(2700);
        });
    }

    public function down(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->dropColumn('imposto_bps');
        });
    }
};
