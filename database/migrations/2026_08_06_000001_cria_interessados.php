<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem pediu contato pela pagina publica.
 *
 * O formulario da campanha grava aqui em vez de abrir o WhatsApp com os dados
 * na URL: nome e telefone sao dado pessoal, e URL de conversa passa por
 * servidor de terceiro e fica em historico de navegador. O dado entra no nosso
 * banco e a conversa comeca do nosso lado.
 *
 * `atendido_em` e o que separa fila de arquivo: interessado sem atendimento e
 * venda esfriando, e o painel da administracao mostra os que ainda esperam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interessados', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 120);
            $t->string('empresa', 150);
            $t->string('telefone', 20);
            $t->string('email', 150);

            // Faixa em texto, nao numero: "6 a 20" e resposta de formulario,
            // e ninguem vai somar funcionarios de interessados.
            $t->string('funcionarios', 20);

            // De onde o pedido veio, para saber qual chamada converte.
            $t->string('origem', 40)->default('campanha');

            $t->timestamp('atendido_em')->nullable();
            $t->timestamps();

            $t->index(['atendido_em', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interessados');
    }
};
