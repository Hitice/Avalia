<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tira o versionamento do catalogo.
 *
 * A decisao de produto e ter UM catalogo, editavel a qualquer momento. O
 * ciclo rascunho -> ativa -> encerrada, com preco congelado, era friccao sem
 * contrapartida: nao existe consulta nem fatura ainda, entao nao havia o que
 * proteger.
 *
 * A garantia de que reajuste nao reescreve cobranca passada continua existindo,
 * so que no lugar certo: consulta e fatura gravam preco e custo no momento da
 * emissao (PDD.md, secoes 7 e 8). Quem cobra guarda o proprio valor; o catalogo
 * diz apenas quanto custa hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->dropIndex(['situacao']);
            $t->dropColumn(['situacao', 'congelada_em', 'vigencia_fim']);
        });
    }

    public function down(): void
    {
        Schema::table('versoes_catalogo', function (Blueprint $t) {
            $t->enum('situacao', ['rascunho', 'agendada', 'ativa', 'encerrada'])
                ->default('rascunho')
                ->index();
            $t->timestamp('congelada_em')->nullable();
            $t->date('vigencia_fim')->nullable();
        });
    }
};
