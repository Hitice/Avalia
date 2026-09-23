<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O repasse deixa de ser um e passa a ser um por parceiro da linha.
 *
 * Antes havia um lancamento de repasse por parcela, porque so o produtor
 * recebia. Com a rede, a mesma parcela paga o vendedor, quem o trouxe e o topo,
 * e cada um precisa da propria linha: sem isso, o extrato de um parceiro seria
 * um numero que ninguem consegue conferir.
 *
 * O indice unico de (parcela, tipo) sai por isso. No lugar entra
 * (parcela, tipo, beneficiario), que continua impedindo o lancamento duplicado
 * do mesmo webhook sem impedir que dois parceiros recebam da mesma parcela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_360', function (Blueprint $t) {
            $t->foreignId('beneficiario_id')->nullable()->after('parcela_360_id')
                ->constrained('produtores')->nullOnDelete();
        });

        // O novo indice nasce ANTES de o antigo sair, e a ordem nao e gosto.
        // No MySQL o unico (parcela, tipo) e o indice que sustenta a chave
        // estrangeira de parcela_360_id, e derrubar o indice que uma FK usa da
        // erro 1553. Com o indice novo ja criado, a FK passa a se apoiar nele e
        // o antigo sai sem reclamacao. No SQLite da suite a ordem era
        // indiferente, e foi por isso que o defeito so apareceu ao publicar.
        Schema::table('lancamentos_360', function (Blueprint $t) {
            $t->unique(['parcela_360_id', 'tipo', 'beneficiario_id']);
        });

        Schema::table('lancamentos_360', function (Blueprint $t) {
            $t->dropUnique(['parcela_360_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_360', function (Blueprint $t) {
            $t->dropUnique(['parcela_360_id', 'tipo', 'beneficiario_id']);
            $t->unique(['parcela_360_id', 'tipo']);
            $t->dropForeign(['beneficiario_id']);
            $t->dropColumn('beneficiario_id');
        });
    }
};
