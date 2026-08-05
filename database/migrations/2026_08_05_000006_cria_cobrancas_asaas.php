<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->string('asaas_customer_id')->nullable()->unique();
        });

        Schema::create('cobrancas_asaas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('fatura_id')->nullable()->constrained('faturas')->nullOnDelete();
            $t->string('asaas_charge_id')->nullable()->unique();
            $t->string('situacao', 40)->default('aguardando_configuracao')->index();
            $t->string('tipo_cobranca', 20)->default('UNDEFINED');
            $t->bigInteger('valor_cents');
            $t->date('vencimento');
            $t->string('invoice_url')->nullable();
            $t->string('bank_slip_url')->nullable();
            $t->text('pix_copia_cola')->nullable();
            $t->json('resposta')->nullable();
            $t->timestamps();

            $t->unique('fatura_id');
        });

        Schema::create('eventos_asaas', function (Blueprint $t) {
            $t->id();
            $t->string('evento_externo')->unique();
            $t->string('tipo', 80)->index();
            $t->foreignId('cobranca_asaas_id')->nullable()->constrained('cobrancas_asaas')->nullOnDelete();
            $t->json('payload');
            $t->timestamp('recebido_em')->index();
            $t->timestamp('processado_em')->nullable();
            $t->text('erro')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_asaas');
        Schema::dropIfExists('cobrancas_asaas');
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('asaas_customer_id'));
    }
};
