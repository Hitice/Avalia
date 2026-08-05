<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->string('acao', 100)->index();
            $t->string('entidade_tipo', 120)->nullable();
            $t->unsignedBigInteger('entidade_id')->nullable();
            $t->json('dados')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamp('ocorreu_em')->index();
            $t->timestamps();

            $t->index(['entidade_tipo', 'entidade_id']);
        });

        Schema::create('documentos', function (Blueprint $t) {
            $t->id();
            $t->string('titulo');
            $t->string('tipo', 60)->index();
            $t->string('versao', 40);
            $t->longText('conteudo');
            $t->boolean('exige_aceite')->default(true);
            $t->boolean('ativo')->default(true)->index();
            $t->timestamps();

            $t->unique(['tipo', 'versao']);
        });

        Schema::create('aceites_documento', function (Blueprint $t) {
            $t->id();
            $t->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->string('versao', 40);
            $t->string('hash_conteudo', 64);
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamp('aceito_em')->index();
            $t->timestamps();

            $t->unique(['documento_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aceites_documento');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('auditoria');
    }
};
