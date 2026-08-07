<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O nome do produto no fornecedor, servico a servico.
 *
 * Cada fornecedor chama a mesma consulta de um jeito (na Serasa,
 * RELATORIO_AVANCADO_PF; em outro bureau, outro nome). O conector le daqui;
 * servico sem codigo do fornecedor nao sai para consulta real e explica o
 * motivo, em vez de chutar um relatorio que o contrato talvez nem tenha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->string('codigo_fornecedor', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->dropColumn('codigo_fornecedor');
        });
    }
};
