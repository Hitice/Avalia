<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O produtor passa a ter conta, e nao so cadastro.
 *
 * Mesmo desenho das outras contas da casa: senha com hash, `sessao_versao`
 * para derrubar sessoes na hora, e `remember_token` para o "manter conectado".
 * O contador de versao e o que torna a revogacao real: sem ele, quem ja estava
 * dentro continuaria dentro depois de ser bloqueado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtores', function (Blueprint $t) {
            $t->string('senha')->nullable()->after('email');
            $t->unsignedInteger('sessao_versao')->default(1)->after('senha');
            $t->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('produtores', function (Blueprint $t) {
            $t->dropColumn(['senha', 'sessao_versao', 'remember_token']);
        });
    }
};
