<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operadores: as pessoas que consultam em nome da empresa cliente.
 *
 * Uma loja com dez atendentes num login so e um problema de LGPD esperando
 * acontecer: alguem consulta errado e ninguem sabe quem foi. Cada operador tem
 * conta propria, subordinada a empresa, e a consulta guarda quem clicou.
 *
 * O aceite tambem e por pessoa: o operador declara ciencia dos termos com o
 * proprio checkbox, alem do aceite contratual da conta master.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operadores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->string('nome', 150);

            // Unico no sistema inteiro: o login e uma porta so, e o mesmo
            // e-mail em duas tabelas tornaria a entrada ambigua.
            $t->string('email', 150)->unique();

            $t->string('senha');
            $t->boolean('ativo')->default(true);
            $t->unsignedInteger('sessao_versao')->default(1);
            $t->timestamp('ultimo_acesso_em')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::table('consultas', function (Blueprint $t) {
            // Quem clicou, quando a consulta veio de um operador. Nulo nas
            // antigas e nas feitas pela conta master.
            $t->foreignId('operador_id')->nullable()->constrained('operadores')->nullOnDelete();
        });

        Schema::table('aceites_documento', function (Blueprint $t) {
            // Aceite de ciencia do operador. Nulo = aceite contratual da
            // conta master, que continua sendo o que vale como contrato.
            $t->foreignId('operador_id')->nullable()->constrained('operadores')->nullOnDelete();

            // O unico por (documento, cliente) valia quando so a master
            // aceitava; agora cada identidade tem a propria linha. O indice
            // novo entra ANTES de o antigo cair: o FK de documento_id precisa
            // de um indice em pe o tempo todo, e no MySQL derrubar o unico que
            // o sustenta sem substituto falha a migration.
            $t->unique(['documento_id', 'cliente_id', 'operador_id']);
            $t->dropUnique(['documento_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::table('aceites_documento', function (Blueprint $t) {
            $t->dropUnique(['documento_id', 'cliente_id', 'operador_id']);
            $t->dropConstrainedForeignId('operador_id');
            $t->unique(['documento_id', 'cliente_id']);
        });
        Schema::table('consultas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('operador_id');
        });
        Schema::dropIfExists('operadores');
    }
};
