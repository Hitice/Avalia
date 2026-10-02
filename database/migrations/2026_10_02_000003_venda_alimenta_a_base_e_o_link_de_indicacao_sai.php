<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venda da plaquinha passa a cadastrar o cliente na base. O link de indicacao
 * por vendedor sai: era redundante, porque a venda ja colhe nome e contato, e
 * estava inerte, porque o codigo ia na URL e controller nenhum o lia.
 *
 * `email`, `responsavel` e `whatsapp` deixam de ser NOT NULL: a venda so tem
 * nome e contato. A obrigatoriedade fica no formulario publico do Google, onde
 * faz sentido, e nao no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $t) {
            $t->string('responsavel', 150)->nullable()->change();
            $t->string('email', 150)->nullable()->change();
            $t->string('whatsapp', 20)->nullable()->change();
        });

        Schema::table('staff', function (Blueprint $t) {
            $t->dropUnique(['codigo_indicacao']);
            $t->dropColumn('codigo_indicacao');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->string('codigo_indicacao', 8)->nullable()->unique()->after('comissao_pct');
        });
    }
};
