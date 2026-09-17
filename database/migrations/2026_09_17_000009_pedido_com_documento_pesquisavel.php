<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impressao digital do documento do comprador, indexada.
 *
 * A regra "uma compra em aberto por documento" precisava procurar por CPF, e o
 * CPF esta cifrado. A busca era feita em PHP: carregar todos os pedidos em
 * aberto da plataforma inteira e decifrar um a um, a cada checkout. Com dez mil
 * pedidos em aberto, dez mil descriptografias por compra, numa rota publica.
 *
 * O hash e HMAC com a chave da aplicacao, e nao hash simples: o espaco de CPFs
 * validos e pequeno o bastante para alguem testar todos contra uma coluna de
 * sha256 puro e descobrir quem comprou o que.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_360', function (Blueprint $t) {
            $t->string('cliente_documento_hash', 64)->nullable()->after('cliente_documento')->index();
        });

        foreach (\App\Models\Pedido360::whereNull('cliente_documento_hash')->cursor() as $pedido) {
            $pedido->updateQuietly([
                'cliente_documento_hash' => \App\Support\Documento::hash($pedido->cliente_documento),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pedidos_360', function (Blueprint $t) {
            $t->dropColumn('cliente_documento_hash');
        });
    }
};
