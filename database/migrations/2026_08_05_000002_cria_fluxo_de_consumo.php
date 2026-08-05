<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O caminho do dinheiro: empresa contratada, consulta feita, fatura fechada.
 *
 * A tabela `clientes` nasceu so com o que o login precisa. Aqui ela ganha o que
 * o comercial decide: qual plano a empresa contratou e de quem e a carteira.
 *
 * `consultas` e `faturas` gravam preco e custo NO MOMENTO DA EMISSAO, em vez de
 * apontar para o catalogo. E essa copia, e nao travar a tabela de precos, que
 * impede um reajuste de hoje de reescrever a cobranca de ontem (PDD.md, secao 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->string('cnpj', 14)->nullable()->unique();

            // Plano e vendedor podem faltar durante o cadastro e nao levam a
            // empresa junto se o plano for removido: historico nao some.
            $t->foreignId('plano_id')->nullable()->constrained('planos')->nullOnDelete();
            $t->foreignId('vendedor_id')->nullable()->constrained('staff')->nullOnDelete();
        });

        Schema::create('consultas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('servico_id')->constrained('servicos');

            // Competencia em texto AAAA-MM: e assim que o fechamento agrupa, e
            // ordenar texto nesse formato ja da ordem cronologica.
            $t->string('competencia', 7)->index();

            // Congelados na emissao. Nunca sao relidos do catalogo.
            $t->bigInteger('preco_cents');
            $t->bigInteger('custo_cents')->nullable();

            $t->timestamps();
            $t->index(['cliente_id', 'competencia']);
        });

        Schema::create('faturas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('vendedor_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->string('competencia', 7);

            // Uma fatura por empresa por mes. O banco recusa a segunda, entao
            // fechar duas vezes nao duplica cobranca.
            $t->unique(['cliente_id', 'competencia']);

            $t->bigInteger('mensalidade_cents');
            $t->bigInteger('consumo_minimo_cents');
            $t->bigInteger('consumo_realizado_cents');
            $t->bigInteger('consumo_faturado_cents');
            $t->bigInteger('total_cents');

            // A aliquota vai junto do valor: ela muda com o tempo e a fatura
            // antiga tem de continuar explicavel sem consultar o catalogo.
            $t->unsignedInteger('imposto_bps');
            $t->bigInteger('imposto_cents');
            $t->bigInteger('custo_cents');
            $t->bigInteger('lucro_cents');
            $t->unsignedInteger('comissao_pct');
            $t->bigInteger('comissao_cents');

            $t->timestamp('fechada_em');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
        Schema::dropIfExists('consultas');

        Schema::table('clientes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('plano_id');
            $t->dropConstrainedForeignId('vendedor_id');
            $t->dropColumn('cnpj');
        });
    }
};
