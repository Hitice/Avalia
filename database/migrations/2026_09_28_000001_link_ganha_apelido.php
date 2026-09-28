<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O link encurtado pode morar na raiz, com nome proprio.
 *
 * `avaliaone.com.br/MarthaNegocios` em vez de `avaliaone.com.br/l/K7M2PX`.
 *
 * Coluna nova, e nao troca do `codigo`: o codigo sorteado continua valendo
 * sempre, e o apelido e um segundo endereco para o mesmo link. Assim um link
 * ja gravado numa tag ganha nome bonito sem que a tag pare de funcionar.
 *
 * Unico porque dois links com o mesmo apelido seriam duas pessoas disputando o
 * mesmo endereco publico, e quem chegasse depois roubaria o trafego do
 * primeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('links', 'apelido')) {
            return;
        }

        Schema::table('links', function (Blueprint $t) {
            $t->string('apelido', 40)->nullable()->unique()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $t) {
            $t->dropUnique(['apelido']);
            $t->dropColumn('apelido');
        });
    }
};
