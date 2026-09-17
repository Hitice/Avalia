<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O livro-caixa do Avalia 360: o que entrou, quanto custou e quanto foi
 * repassado, lancamento a lancamento.
 *
 * Imutavel por construcao. Nao existe `updated_at`, e nada aqui e corrigido no
 * lugar: estorno e um lancamento novo de sinal contrario, do mesmo jeito que a
 * contabilidade faz ha seiscentos anos. Editar lancamento antigo apaga a
 * explicacao de um saldo que alguem ja conferiu.
 *
 * Saldo nao e coluna em lugar nenhum. Ele e a soma daqui, sempre. Coluna de
 * saldo e a primeira coisa a divergir do extrato, e quando diverge ninguem
 * sabe qual dos dois esta certo.
 *
 * `valor_cents` e assinado: entrada positiva, saida negativa. Assim somar a
 * coluna inteira responde "quanto sobrou" sem regra de sinal espalhada pelo
 * codigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos_360', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_360_id')->constrained('pedidos_360')->restrictOnDelete();
            $t->foreignId('parcela_360_id')->nullable()->constrained('parcelas_360')->nullOnDelete();

            // bruto, taxa_provedor, taxa_plataforma, repasse, estorno
            $t->string('tipo', 20);

            $t->bigInteger('valor_cents');

            // Quando o dinheiro se moveu de verdade, que nao e quando a linha
            // foi escrita: webhook atrasado lanca hoje um pagamento de ontem.
            $t->timestamp('ocorrido_em');

            // O evento do provedor que originou a linha. Guardado para
            // reconciliar e para impedir que o mesmo evento lance duas vezes.
            $t->foreignId('evento_asaas_id')->nullable()->constrained('eventos_asaas')->nullOnDelete();

            $t->string('descricao', 150)->nullable();

            // So a data de criacao: linha de razao nao se atualiza.
            $t->timestamp('created_at')->useCurrent();

            $t->index(['pedido_360_id', 'tipo']);
            $t->index('ocorrido_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos_360');
    }
};
