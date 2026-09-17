<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha de cada tipo por parcela, garantido pelo banco.
 *
 * A protecao contra pagar duas vezes era um SELECT antes do INSERT, fora da
 * transacao. Dois workers atendendo PAYMENT_CONFIRMED e PAYMENT_RECEIVED da
 * mesma parcela ao mesmo tempo passavam os dois pela checagem e o razao ganhava
 * oito linhas em vez de quatro, com bruto e repasse dobrados.
 *
 * O pior desse erro e que ele nao se anuncia: a invariante da soma zero
 * continua valendo com as linhas dobradas, entao a conferencia que existia nao
 * pegaria. Agora quem impede e o indice, que nao depende de quem chegou
 * primeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_360', function (Blueprint $t) {
            $t->unique(['parcela_360_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_360', function (Blueprint $t) {
            $t->dropUnique(['parcela_360_id', 'tipo']);
        });
    }
};
