<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta de demonstracao, feita pelo vendedor no proprio ambiente.
 *
 * "Deixa eu fazer uma do seu CNPJ para te mostrar como sai" fecha venda, e ate
 * aqui exigia entrar no login de um cliente. A demonstracao e do vendedor:
 * cliente nulo, preco zero (ninguem e cobrado) e o custo real congelado, que
 * sai da comissao a receber dele. E a regra definida pelo negocio: desconta da
 * comissao, pelo preco de custo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $t) {
            $t->foreignId('vendedor_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vendedor_id');
            $t->foreignId('cliente_id')->nullable(false)->change();
        });
    }
};
