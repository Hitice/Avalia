<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numero curto e estavel por servico, para gente falar ao telefone.
 *
 * "Consulta 7" se dita e se anota; o codigo tecnico (scpc-bvs) e para
 * integracao, nao para atendimento. O numero nasce sequencial, nunca muda e
 * nunca se reusa: reaproveitar o numero de um servico morto faria um pedido
 * antigo apontar para outro produto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->unsignedSmallInteger('numero')->nullable()->unique();
        });

        // Os existentes ganham numero em ordem alfabetica, que e a ordem em
        // que as telas os mostravam ate aqui.
        $numero = 1;

        foreach (DB::table('servicos')->orderBy('nome')->pluck('id') as $id) {
            DB::table('servicos')->where('id', $id)->update(['numero' => $numero++]);
        }
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->dropColumn('numero');
        });
    }
};
