<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem cada documento alcanca, e aceite tambem para a equipe.
 *
 * A administracao define o publico de cada termo (empresa, operador,
 * vendedor); exige_aceite continua separando o vinculante do material de
 * apoio, que e somente leitura. O aceite de vendedor entra na mesma tabela de
 * evidencias, com staff_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $t) {
            $t->boolean('para_empresa')->default(true);
            $t->boolean('para_operador')->default(true);
            $t->boolean('para_vendedor')->default(false);
        });

        Schema::table('aceites_documento', function (Blueprint $t) {
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('aceites_documento', function (Blueprint $t) {
            $t->dropConstrainedForeignId('staff_id');
            $t->foreignId('cliente_id')->nullable(false)->change();
        });
        Schema::table('documentos', function (Blueprint $t) {
            $t->dropColumn(['para_empresa', 'para_operador', 'para_vendedor']);
        });
    }
};
