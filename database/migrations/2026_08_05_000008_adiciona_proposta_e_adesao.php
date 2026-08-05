<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->string('telefone', 20)->nullable()->after('email');
            $t->string('responsavel_nome')->nullable()->after('telefone');
            $t->string('responsavel_cpf', 14)->nullable()->after('responsavel_nome');
            $t->string('cep', 8)->nullable()->after('responsavel_cpf');
            $t->string('logradouro')->nullable()->after('cep');
            $t->string('numero', 20)->nullable()->after('logradouro');
            $t->string('complemento')->nullable()->after('numero');
            $t->string('bairro')->nullable()->after('complemento');
            $t->string('cidade')->nullable()->after('bairro');
            $t->string('uf', 2)->nullable()->after('cidade');
            $t->string('vigencia_tipo', 30)->nullable()->after('vendedor_id');
            $t->date('contrato_inicio')->nullable()->after('vigencia_tipo');
            $t->date('contrato_fim')->nullable()->after('contrato_inicio');
            $t->date('carencia_ate')->nullable()->after('contrato_fim');
        });

        Schema::create('adesoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->unique()->constrained('clientes')->cascadeOnDelete();
            $t->bigInteger('valor_cents')->default(0);
            $t->unsignedSmallInteger('parcelas')->default(1);
            $t->bigInteger('valor_parcela_cents')->default(0);
            $t->bigInteger('vendedor_cents')->default(0);
            $t->bigInteger('avalia_cents')->default(0);
            $t->timestamp('liquidada_em')->nullable();
            $t->timestamps();
        });

        Schema::table('cobrancas_asaas', function (Blueprint $t) {
            $t->foreignId('adesao_id')->nullable()->after('fatura_id')->constrained('adesoes')->nullOnDelete();
            $t->unsignedSmallInteger('parcela')->nullable()->after('adesao_id');
            $t->unique(['adesao_id', 'parcela']);
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas_asaas', function (Blueprint $t) {
            $t->dropUnique(['adesao_id', 'parcela']);
            $t->dropConstrainedForeignId('adesao_id');
            $t->dropColumn('parcela');
        });
        Schema::dropIfExists('adesoes');
        Schema::table('clientes', function (Blueprint $t) {
            $t->dropColumn(['telefone', 'responsavel_nome', 'responsavel_cpf', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'vigencia_tipo', 'contrato_inicio', 'contrato_fim', 'carencia_ate']);
        });
    }
};
