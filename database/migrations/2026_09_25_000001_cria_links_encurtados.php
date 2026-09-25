<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links encurtados.
 *
 * Tabela propria, e nao uma linha a mais em `etiquetas`. Os dois redirecionam
 * um codigo curto, mas sao coisas diferentes: a etiqueta e produto vendido,
 * com campanha, vencimento e pacote para a grafica, e o link e ferramenta de
 * bancada, que nasce e morre sem nada disso. Juntos, a lista de etiquetas
 * passaria a misturar o que se cobra com o que nao se cobra, e o vencimento
 * teria que aprender a nao valer para metade das linhas.
 *
 * O prefixo do endereco tambem e outro, `/l/` em vez de `/q/`, entao um codigo
 * sorteado aqui pode coincidir com um de la sem consequencia: sao dois espacos
 * de nomes.
 *
 * A razao de existir e a tag NFC: as que a casa usa tem cerca de 140 bytes
 * uteis, e um endereco de campanha com parametros de origem nao cabe.
 *
 * `hasTable` pela mesma licao das outras: publicacao que roda duas vezes ao
 * mesmo tempo cria a tabela sem registrar a migration, e dai toda publicacao
 * falha em "table already exists" e volta atras sozinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('links') || Schema::create('links', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 6)->unique();
            $t->string('destino', 1000);
            $t->string('titulo', 120)->nullable();

            // Desligar em vez de apagar: o link ja pode estar gravado numa tag
            // que esta na mao de alguem, e o codigo nunca volta ao sorteio.
            $t->boolean('ativo')->default(true);

            $t->unsignedBigInteger('cliques')->default(0);
            $t->timestamp('ultimo_clique_em')->nullable();

            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
