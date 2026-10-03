<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que a sexta-feira precisa saber: qual comissao de consulta ja foi paga,
 * qual demonstracao ja foi descontada do repasse, e as contas a pagar da casa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $t) {
            $t->timestamp('comissao_paga_em')->nullable()->after('comissao_liberada_em');
        });

        Schema::table('consultas', function (Blueprint $t) {
            $t->timestamp('descontada_em')->nullable()->after('custo_cents');
        });

        Schema::create('contas_a_pagar', function (Blueprint $t) {
            $t->id();
            $t->string('descricao', 200);
            $t->string('fornecedor', 150)->nullable();
            $t->foreignId('categoria_id')->constrained('contas_financeiras');
            $t->bigInteger('valor_cents');
            $t->date('vence_em');
            $t->timestamp('pago_em')->nullable();
            // A provisao e o pagamento, cada um com a sua linha no razao.
            $t->foreignId('lancamento_id')->nullable()->constrained('lancamentos_financeiros');
            $t->foreignId('pagamento_id')->nullable()->constrained('lancamentos_financeiros');
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();
            $t->index(['pago_em', 'vence_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_a_pagar');
        Schema::table('consultas', fn (Blueprint $t) => $t->dropColumn('descontada_em'));
        Schema::table('faturas', fn (Blueprint $t) => $t->dropColumn('comissao_paga_em'));
    }
};
