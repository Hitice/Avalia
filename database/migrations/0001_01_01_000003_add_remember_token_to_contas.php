<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna que sustenta o "manter conectado".
 *
 * O guard de sessao grava aqui o token do cookie de lembranca. Sem a coluna,
 * o login com "lembrar" marcado quebra em 500, e como a caixa vem marcada por
 * padrao, quebrava para praticamente todo mundo.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['staff', 'clientes'] as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->rememberToken();
            });
        }
    }

    public function down(): void
    {
        foreach (['staff', 'clientes'] as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropColumn('remember_token');
            });
        }
    }
};
