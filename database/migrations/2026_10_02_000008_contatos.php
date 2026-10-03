<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma pessoa ou empresa so, para as cinco tabelas que a cadastravam cada uma
 * do seu jeito. As cinco ficam e ganham contato_id; o que e de cada frente
 * (plano, subconta, place_id) continua onde esta. Nada se apaga.
 */
return new class extends Migration
{
    private const COM_CONTATO = ['clientes', 'negocios', 'leads', 'interessados', 'produtores'];

    public function up(): void
    {
        Schema::create('contatos', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 200);
            $t->string('documento', 20)->nullable()->index();
            $t->string('email', 150)->nullable()->index();
            $t->string('whatsapp', 20)->nullable()->index();
            $t->string('telefone', 20)->nullable();
            $t->string('cidade', 120)->nullable();
            $t->string('uf', 2)->nullable();
            $t->string('origem', 30)->nullable();
            $t->timestamps();
        });

        Schema::create('vinculos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contato_id')->constrained('contatos')->cascadeOnDelete();
            $t->string('papel', 30);
            $t->string('entidade_tipo', 40);
            $t->unsignedBigInteger('entidade_id');
            $t->timestamps();
            $t->unique(['entidade_tipo', 'entidade_id']);
            $t->index(['contato_id', 'papel']);
        });

        Schema::create('interacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contato_id')->constrained('contatos')->cascadeOnDelete();
            $t->string('tipo', 30);
            $t->string('descricao', 300);
            $t->timestamp('ocorrido_em');
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();
            $t->index(['contato_id', 'ocorrido_em']);
        });

        foreach (self::COM_CONTATO as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->foreignId('contato_id')->nullable()->constrained('contatos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::COM_CONTATO as $tabela) {
            Schema::table($tabela, fn (Blueprint $t) => $t->dropConstrainedForeignId('contato_id'));
        }

        Schema::dropIfExists('interacoes');
        Schema::dropIfExists('vinculos');
        Schema::dropIfExists('contatos');
    }
};
