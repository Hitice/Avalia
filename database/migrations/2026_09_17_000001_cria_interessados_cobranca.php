<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem pediu para vender parcelado pelo Avalia One.
 *
 * Tabela separada de `interessados` de proposito: o que se pergunta a um
 * produtor (documento, ticket medio, volume) nao e o que se pergunta a quem
 * quer consultar score. Numa tabela so, metade das colunas ficaria nula em
 * cada linha e o painel teria que adivinhar de qual produto veio o pedido.
 *
 * `atendido_em` separa fila de arquivo, igual em `interessados`: pre-cadastro
 * sem retorno e venda esfriando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interessados_cobranca', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 120);

            // Cifrados pela aplicacao: sao os dados pessoais do pedido, e
            // backup de banco que vaza nao pode entregar CPF nem telefone de
            // ninguem. Texto cifrado nao cabe em coluna curta, por isso `text`.
            $t->text('documento');
            $t->text('whatsapp');

            // O e-mail fica em claro, e e o unico que fica: e a chave que
            // impede o mesmo produtor se cadastrar cinco vezes, e coluna
            // cifrada nao se pesquisa nem se indexa.
            $t->string('email', 150)->unique();

            // Dinheiro em centavos, como em todo o resto do sistema.
            $t->unsignedBigInteger('ticket_medio_cents');

            // Faixa em texto pelo mesmo motivo de `funcionarios` la em
            // `interessados`: e resposta de formulario, e ninguem vai somar.
            $t->string('volume_mensal', 30);

            $t->string('origem', 40)->default('cobranca');
            $t->timestamp('atendido_em')->nullable();
            $t->timestamps();

            $t->index(['atendido_em', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interessados_cobranca');
    }
};
