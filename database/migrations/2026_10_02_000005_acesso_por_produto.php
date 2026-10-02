<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Que produto cada pessoa da equipe acessa: Avalia One, Avalia Sales, ou os
 * dois. Nasce ligado para todos, porque hoje todo mundo entra em tudo, e a
 * administracao desliga pessoa a pessoa na tela de Equipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->boolean('acessa_one')->default(true)->after('pode_socios');
            $t->boolean('acessa_sales')->default(true)->after('acessa_one');
        });
    }

    public function down(): void
    {
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn(['acessa_one', 'acessa_sales']));
    }
};
