<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venda parcelada e as parcelas que ela gera.
 *
 * O pedido guarda o preco, o parcelamento e a taxa COPIADOS da oferta no
 * momento da compra. A oferta pode mudar amanha; o que foi contratado nao.
 *
 * A ordem das situacoes conta a historia da venda, e nenhuma delas volta
 * atras: em_analise, reprovado ou aguardando_contrato, aguardando_entrada,
 * efetivado, cancelado. Parcela so nasce depois de `efetivado`, que exige
 * contrato assinado E entrada confirmada. Emitir boleto antes disso e cobrar
 * por um contrato que ninguem assinou.
 *
 * O dado pessoal do cliente final mora aqui cifrado. Nao existe cadastro de
 * cliente separado de proposito: quem compra de um produtor nao vira base da
 * Avalia One, e cada pedido carrega o que precisa para cobrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_360', function (Blueprint $t) {
            $t->id();
            $t->foreignId('oferta_360_id')->constrained('ofertas_360')->restrictOnDelete();
            $t->foreignId('produtor_id')->constrained('produtores')->restrictOnDelete();

            $t->string('cliente_nome', 150);
            $t->text('cliente_documento');
            $t->string('cliente_email', 150);
            $t->text('cliente_telefone');
            $t->date('cliente_nascimento')->nullable();
            $t->text('cliente_endereco')->nullable();

            $t->string('situacao', 25)->default('em_analise');
            $t->string('situacao_financeira', 20)->default('adimplente');

            // Copiados da oferta na compra.
            $t->unsignedBigInteger('valor_total_cents');
            $t->unsignedBigInteger('entrada_cents');
            $t->unsignedSmallInteger('parcelas');
            $t->unsignedBigInteger('valor_parcela_cents');

            // Taxa da plataforma em pontos base: 5% sao 500. Inteiro, porque
            // percentual em float vira divergencia de centavo no repasse.
            $t->unsignedInteger('taxa_bps');

            // Dia do mes que o cliente escolheu para as parcelas vencerem.
            $t->unsignedTinyInteger('melhor_dia')->nullable();

            // A decisao da analise, com a versao da regra que decidiu: sem
            // isso, mudar a regra torna impossivel explicar a recusa de ontem.
            $t->string('analise_versao', 20)->nullable();
            $t->string('analise_motivo', 150)->nullable();
            $t->timestamp('analise_em')->nullable();

            $t->timestamp('contrato_assinado_em')->nullable();
            $t->timestamp('efetivado_em')->nullable();
            $t->timestamp('cancelado_em')->nullable();
            $t->string('cancelamento_motivo', 150)->nullable();

            $t->string('asaas_customer_id', 60)->nullable();

            $t->timestamps();

            $t->index(['produtor_id', 'situacao']);
            $t->index(['situacao', 'created_at']);
        });

        Schema::create('parcelas_360', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_360_id')->constrained('pedidos_360')->cascadeOnDelete();

            // Zero e a entrada. As demais seguem a ordem do carne, e o par
            // pedido + numero e unico: reprocessar webhook nao duplica parcela.
            $t->unsignedSmallInteger('numero');

            $t->unsignedBigInteger('valor_cents');
            $t->date('vencimento');
            $t->string('situacao', 20)->default('aberta');
            $t->timestamp('paga_em')->nullable();

            $t->foreignId('cobranca_asaas_id')->nullable()->constrained('cobrancas_asaas')->nullOnDelete();

            $t->timestamps();

            $t->unique(['pedido_360_id', 'numero']);
            $t->index(['situacao', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelas_360');
        Schema::dropIfExists('pedidos_360');
    }
};
