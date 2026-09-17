<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que o produtor vende, e em que condicoes.
 *
 * Produto e a coisa vendida; oferta e uma forma de vende-la. O mesmo curso
 * pode ter oferta de 12x com entrada e outra de 6x sem, e cada oferta tem o
 * proprio link de checkout. Separar os dois evita o erro classico de mudar o
 * parcelamento e junto, sem querer, mudar o preco de quem ja comprou.
 *
 * Preco e parcelamento sao copiados para o pedido na hora da compra: reajuste
 * de hoje nao pode mexer em venda de ontem, mesma regra que vale para consulta
 * e fatura no resto do sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_360', function (Blueprint $t) {
            $t->id();
            $t->foreignId('produtor_id')->constrained('produtores')->cascadeOnDelete();
            $t->string('nome', 150);
            $t->text('descricao')->nullable();
            $t->unsignedBigInteger('valor_cents');

            // Prazo em que o cliente pode desistir e receber a entrada de
            // volta. Por produto, porque curso e servico tem praticas
            // diferentes, e o minimo legal do CDC nao e o mesmo do mercado.
            $t->unsignedSmallInteger('dias_arrependimento')->default(7);

            $t->boolean('ativo')->default(true);
            $t->timestamps();

            $t->index(['produtor_id', 'ativo']);
        });

        Schema::create('ofertas_360', function (Blueprint $t) {
            $t->id();
            $t->foreignId('produto_360_id')->constrained('produtos_360')->cascadeOnDelete();
            $t->string('titulo', 150);

            $t->unsignedBigInteger('valor_cents');
            $t->unsignedSmallInteger('parcelas');
            $t->unsignedBigInteger('entrada_cents');

            // Dias ate o vencimento do boleto de entrada. O provedor recusa
            // menos de 3, e mais de 60 deixa a venda pendurada tempo demais.
            $t->unsignedSmallInteger('entrada_em_dias')->default(7);

            // O endereco publico do checkout. Palavra, nao numero: link com id
            // sequencial revela quantas ofertas existem e convida a passear
            // pelas dos outros.
            $t->string('slug', 80)->unique();

            $t->boolean('ativa')->default(true);
            $t->timestamps();

            $t->index(['produto_360_id', 'ativa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofertas_360');
        Schema::dropIfExists('produtos_360');
    }
};
