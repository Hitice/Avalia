<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A base de prospeccao: empresa que ainda nao contratou nada.
 *
 * Tabela propria, e nao uma situacao a mais em `clientes`. Lead nao tem plano,
 * nao tem fatura, nao tem senha e nao entra em consulta nenhuma: enfia-lo na
 * tabela de contratante deixaria metade das colunas vazia e um `whereNull`
 * esquecido bastaria para um lead aparecer no fechamento de competencia.
 *
 * `cnpj` guarda so os digitos, do mesmo jeito que a tabela de clientes: e o que
 * permite comparar as duas e descobrir que o lead ja virou cliente.
 *
 * A distribuicao mora na tabela de ligacao, e nao numa coluna `vendedor_id`. Um
 * lead pode ser trabalhado por mais de um vendedor, e a administracao precisa
 * saber quando cada compartilhamento aconteceu e quem decidiu: um `vendedor_id`
 * sobrescrito nao responde nada disso (PDD.md: distribuir lead e decisao da
 * administracao).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $t) {
            $t->id();

            // O identificador que o lead tinha na base de origem. Serve para
            // reimportar sem duplicar, e para conferir contra o PDF original.
            $t->string('codigo', 20)->nullable()->unique();

            $t->string('nome', 160);
            $t->string('cnpj', 20)->nullable()->index();
            $t->string('cidade', 80)->nullable();
            $t->string('uf', 2)->nullable()->index();

            // Campo largo porque a base traz dois numeros na mesma linha
            // ("(62) 3297-1288 / (62) 3225-5473"). Separar em duas colunas
            // inventaria uma ordem de importancia que a origem nao tem.
            $t->string('telefone', 60)->nullable();

            $t->string('email', 160)->nullable();
            $t->string('origem', 40)->nullable()->index();

            // Situacao cadastral que veio da origem. Lead inativo continua na
            // base: e historico da prospeccao, e some do trabalho pelo filtro.
            $t->boolean('ativo')->default(true)->index();

            $t->text('observacao')->nullable();

            $t->timestamps();

            // Remover lead e tira-lo do trabalho, nao apagar: o rastro de
            // quem ja foi compartilhado com quem continua de pe.
            $t->softDeletes();

            $t->index(['deleted_at', 'nome']);
        });

        Schema::create('lead_staff', function (Blueprint $t) {
            $t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            // Quem distribuiu e quando. Sem isto, "por que este lead esta com
            // este vendedor" so se responde procurando na trilha.
            $t->foreignId('compartilhado_por')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamp('compartilhado_em');

            $t->primary(['lead_id', 'staff_id']);
            $t->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_staff');
        Schema::dropIfExists('leads');
    }
};
