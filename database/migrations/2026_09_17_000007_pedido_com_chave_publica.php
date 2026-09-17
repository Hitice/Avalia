<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O pedido do 360 ganha chave publica opaca.
 *
 * A pagina de resultado do checkout e alcancavel sem login, porque quem acabou
 * de comprar nao tem conta. Com o id sequencial na URL, trocar o numero
 * mostrava a compra do vizinho: nome de quem comprou, valor, parcelamento e o
 * link do boleto. Um laco de 1 a N varria a base de compradores de todos os
 * produtores.
 *
 * A chave e ULID: opaca, do tamanho certo para caber num link de WhatsApp e
 * impossivel de adivinhar por incremento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_360', function (Blueprint $t) {
            $t->ulid('chave')->nullable()->after('id')->unique();
        });

        foreach (\App\Models\Pedido360::whereNull('chave')->get() as $pedido) {
            $pedido->update(['chave' => (string) \Illuminate\Support\Str::ulid()]);
        }
    }

    public function down(): void
    {
        Schema::table('pedidos_360', function (Blueprint $t) {
            $t->dropColumn('chave');
        });
    }
};
