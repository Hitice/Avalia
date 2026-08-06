<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa a permissao financeira do administrador generico.
 *
 * Confirmar pagamento a mao libera comissao sem que dinheiro tenha entrado, e
 * fechar competencia emite cobranca. Hoje quem mexe no catalogo faz as duas
 * coisas, porque "admin" e um papel so.
 *
 * A permissao nasce NEGADA e e concedida uma a uma. Quem ja esta cadastrado
 * como administrador recebe agora, para nao perder acesso do dia para a noite:
 * a separacao vale para quem entrar daqui em diante, e para quem a
 * administracao decidir revogar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->boolean('pode_financeiro')->default(false)->after('comissao_pct');
        });

        DB::table('staff')->where('papel', 'admin')->update(['pode_financeiro' => true]);
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->dropColumn('pode_financeiro');
        });
    }
};
