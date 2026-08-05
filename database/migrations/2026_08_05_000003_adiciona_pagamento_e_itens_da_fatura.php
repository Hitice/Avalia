<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $t) {
            // A cobrança pode existir antes de estar paga. Comissão e liberação
            // do acesso leem esta situação, não apenas a data de fechamento.
            $t->string('situacao_pagamento', 20)->default('pendente')->index();
            $t->timestamp('liquidada_em')->nullable()->index();
            $t->timestamp('comissao_liberada_em')->nullable()->index();

            // Valores congelados para explicar como a franquia afetou a conta.
            $t->bigInteger('consumo_bruto_cents')->default(0);
            $t->bigInteger('franquia_cents')->default(0);
            $t->bigInteger('consumo_excedente_cents')->default(0);
        });

        Schema::create('itens_fatura', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $t->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $t->string('servico_nome');
            $t->unsignedInteger('quantidade');
            $t->unsignedInteger('quantidade_franquia');
            $t->unsignedInteger('quantidade_excedente');
            $t->bigInteger('valor_bruto_cents');
            $t->bigInteger('valor_franquia_cents');
            $t->bigInteger('valor_excedente_cents');
            $t->bigInteger('custo_cents');
            $t->timestamps();

            $t->unique(['fatura_id', 'servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_fatura');

        Schema::table('faturas', function (Blueprint $t) {
            $t->dropColumn([
                'situacao_pagamento', 'liquidada_em', 'comissao_liberada_em',
                'consumo_bruto_cents', 'franquia_cents', 'consumo_excedente_cents',
            ]);
        });
    }
};
