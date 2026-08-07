<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O nome da entidade, congelado no momento do registro.
 *
 * "Empresa #1" nao diz nada a quem le a trilha, e resolver o nome na leitura
 * falharia para registro de coisa ja removida. O nome entra junto com o
 * registro e fica: a trilha se explica sozinha para sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria', function (Blueprint $t) {
            $t->string('entidade_rotulo', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('auditoria', function (Blueprint $t) {
            $t->dropColumn('entidade_rotulo');
        });
    }
};
