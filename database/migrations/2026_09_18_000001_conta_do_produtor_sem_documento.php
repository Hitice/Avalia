<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O documento sai da criacao da conta.
 *
 * Pedir CPF ou CNPJ na primeira tela custa caro: e o campo que faz a pessoa
 * parar para buscar o cartao, e quem para raramente volta. Ele continua sendo
 * obrigatorio, so que no momento em que faz falta de verdade, que e a abertura
 * da conta de recebimento no provedor.
 *
 * Coluna nullable em vez de removida: os produtores ja cadastrados com
 * documento continuam com ele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtores', function (Blueprint $t) {
            $t->text('documento')->nullable()->change();
            $t->text('whatsapp')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('produtores', function (Blueprint $t) {
            $t->text('documento')->nullable(false)->change();
            $t->text('whatsapp')->nullable(false)->change();
        });
    }
};
