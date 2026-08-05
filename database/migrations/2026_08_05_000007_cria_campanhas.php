<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanhas', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->text('oferta');
            $t->date('inicio');
            $t->date('fim')->nullable();
            $t->boolean('ativa')->default(true)->index();
            $t->timestamps();
        });

        Schema::create('campanha_cliente', function (Blueprint $t) {
            $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->primary(['campanha_id', 'cliente_id']);
        });

        Schema::create('campanha_servico', function (Blueprint $t) {
            $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
            $t->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $t->primary(['campanha_id', 'servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanha_servico');
        Schema::dropIfExists('campanha_cliente');
        Schema::dropIfExists('campanhas');
    }
};
