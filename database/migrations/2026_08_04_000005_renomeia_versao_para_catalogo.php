<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O versionamento saiu, o nome ficou.
 *
 * A tabela guarda UM catalogo com os parametros comerciais. Chamar de "versoes"
 * fazia todo leitor procurar um ciclo de versoes que nao existe mais, e a
 * coluna catalogo_id em precos e planos espalhava a confusao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('versoes_catalogo', 'catalogos');

        Schema::table('precos', function (Blueprint $t) {
            $t->renameColumn('versao_id', 'catalogo_id');
        });

        Schema::table('planos', function (Blueprint $t) {
            $t->renameColumn('versao_id', 'catalogo_id');
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $t) {
            $t->renameColumn('versao_id', 'catalogo_id');
        });

        Schema::table('precos', function (Blueprint $t) {
            $t->renameColumn('versao_id', 'catalogo_id');
        });

        Schema::rename('versoes_catalogo', 'catalogos');
    }
};
