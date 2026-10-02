<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O Place ID do negocio e o link curto de avaliacao que sai dele.
 *
 * O Place ID fica GRAVADO porque cada busca na Places API e cobrada pelo Google:
 * sem guardar, reimprimir a placa de um cliente antigo custaria uma consulta de
 * novo. E ele nao muda quando o dono renomeia o estabelecimento, entao guardar o
 * identificador vale mais que guardar o nome.
 *
 * O link curto aponta para `links`, e nao e uma coluna de texto: assim o clique
 * e contado pelo encurtador que a casa ja tem, e o cliente pode ver quanta gente
 * abriu o pedido de avaliacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $t) {
            $t->string('place_id', 255)->nullable()->after('instagram');
            $t->foreignId('link_avaliacao_id')->nullable()->after('place_id')
                ->constrained('links')->nullOnDelete();

            $t->index('place_id');
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $t) {
            $t->dropConstrainedForeignId('link_avaliacao_id');
            $t->dropIndex(['place_id']);
            $t->dropColumn('place_id');
        });
    }
};
