<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem vende parcelado pelo Avalia 360.
 *
 * O produtor tem subconta propria no provedor de cobranca: e para ela que o
 * split manda a parte dele de cada parcela, e e por isso que o dinheiro do
 * cliente final nunca passa pela conta da Avalia One. Sem subconta, o produtor
 * fica `pendente` e nao pode publicar oferta.
 *
 * `asaas_api_key` e a chave DA SUBCONTA, devolvida uma unica vez na criacao.
 * Guardada cifrada, como toda credencial no sistema. Perde-la significa pedir
 * outra ao provedor; ela nao se consulta depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtores', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 150);

            // Dado pessoal cifrado, como no pre-cadastro que originou o
            // produtor. O e-mail fica em claro porque e a chave de contato e
            // de deduplicacao.
            $t->text('documento');
            $t->text('whatsapp');
            $t->string('email', 150)->unique();

            $t->string('situacao', 20)->default('pendente');

            // Identificadores do provedor. Ficam nulos ate a subconta existir,
            // e e a presenca do wallet que autoriza publicar oferta.
            $t->string('asaas_account_id', 60)->nullable();
            $t->string('asaas_wallet_id', 60)->nullable();
            $t->text('asaas_api_key')->nullable();

            // De onde veio, quando veio do formulario publico.
            $t->foreignId('interessado_cobranca_id')->nullable()->constrained('interessados_cobranca')->nullOnDelete();

            $t->timestamp('aprovado_em')->nullable();
            $t->timestamps();

            $t->index(['situacao', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtores');
    }
};
