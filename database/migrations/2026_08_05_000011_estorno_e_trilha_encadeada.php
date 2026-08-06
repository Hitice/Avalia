<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estorno de liquidacao e trilha de auditoria encadeada.
 *
 * `estornada_em` na fatura registra que um recebimento foi desfeito. Nao apaga
 * a liquidacao anterior: a competencia continua fechada com o mesmo valor, e o
 * que voltou atras foi o recebimento, nao a venda.
 *
 * Na auditoria entram dois resumos criptograficos. Cada registro guarda o
 * resumo do anterior e o proprio, formando uma corrente: alterar ou remover uma
 * linha no meio quebra a verificacao de todas as seguintes.
 *
 * Isso nao impede a alteracao, porque a tabela continua sendo uma tabela.
 * Impede que ela passe despercebida, que e o que se espera de uma trilha e o
 * que custa caro obter de outro jeito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $t) {
            $t->timestamp('estornada_em')->nullable()->after('liquidada_em');
        });

        Schema::table('auditoria', function (Blueprint $t) {
            $t->string('resumo_anterior', 64)->nullable()->after('ocorreu_em');
            $t->string('resumo', 64)->nullable()->after('resumo_anterior');
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $t) {
            $t->dropColumn('estornada_em');
        });

        Schema::table('auditoria', function (Blueprint $t) {
            $t->dropColumn(['resumo_anterior', 'resumo']);
        });
    }
};
