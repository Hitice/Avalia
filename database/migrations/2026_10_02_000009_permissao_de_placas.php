<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gerar placas, entregar e recolher: so quem cuida da producao. Nasce negada; o superusuario passa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', fn (Blueprint $t) => $t->boolean('pode_placas')->default(false)->after('pode_socios'));
    }

    public function down(): void
    {
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn('pode_placas'));
    }
};
