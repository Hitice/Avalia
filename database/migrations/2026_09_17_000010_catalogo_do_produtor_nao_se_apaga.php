<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produtor, produto e oferta passam a ser desativados, nunca apagados.
 *
 * E a regra que vale para cliente, vendedor e lead no resto do sistema:
 * remover e tirar de circulacao, porque venda antiga precisa continuar
 * explicavel. Aqui isso pesa mais: o slug da oferta ja foi distribuido em
 * links de checkout, e apagar a oferta transformaria um link que alguem tem no
 * WhatsApp num 404 sem explicacao.
 *
 * As chaves seguem em cascata de proposito. Com `deleted_at`, o `delete()` do
 * dia a dia nao chega ao banco, entao a cascata so dispara em `forceDelete`,
 * que e ato deliberado de quem sabe o que esta apagando.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['produtores', 'produtos_360', 'ofertas_360'] as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['produtores', 'produtos_360', 'ofertas_360'] as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
