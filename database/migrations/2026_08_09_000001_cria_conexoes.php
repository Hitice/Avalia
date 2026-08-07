<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As conexoes com servicos externos: cobranca, bureaus, veicular.
 *
 * Credencial sai do .env e entra aqui, criptografada com a chave da aplicacao:
 * na hospedagem compartilhada o .env so se edita por SSH, e trocar uma chave de
 * API nao pode depender de acesso ao servidor. O .env continua valendo como
 * reserva para o que ja estava la.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conexoes', function (Blueprint $t) {
            $t->id();

            // Um registro por fornecedor: a tela oferece a lista suportada
            // (App\Support\Fornecedores), nao ha fornecedor livre.
            $t->string('fornecedor', 40)->unique();

            $t->string('ambiente', 20)->default('homologacao');

            // JSON criptografado. O conteudo varia por fornecedor: chave de
            // API, usuario e senha, client id e secret, URL contratada.
            $t->text('credenciais')->nullable();

            $t->boolean('ativa')->default(false);

            // O resultado do ultimo teste fica gravado: "funcionava as 14h de
            // ontem" e informacao de diagnostico, nao enfeite.
            $t->timestamp('testada_em')->nullable();
            $t->boolean('teste_ok')->nullable();
            $t->string('teste_detalhe', 300)->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conexoes');
    }
};
