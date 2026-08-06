<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O aceite ganha o nome de quem clicou.
 *
 * A conta e da empresa, mas quem aceita e uma pessoa, e e dela que se cobra
 * explicacao se o aceite for contestado. Mesmo desenho da consulta, que ja
 * registra o solicitante. Nullable porque os aceites anteriores a esta coluna
 * nao tem como ganhar um nome retroativo honesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aceites_documento', function (Blueprint $t) {
            $t->string('responsavel', 150)->nullable()->after('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('aceites_documento', function (Blueprint $t) {
            $t->dropColumn('responsavel');
        });
    }
};
