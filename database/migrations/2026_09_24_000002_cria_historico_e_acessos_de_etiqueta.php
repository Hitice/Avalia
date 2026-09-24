<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que a plaquinha ja apontou, ja rendeu e quanto foi lida.
 *
 * O historico de destino existe porque "para onde essa placa apontava em
 * marco" e uma pergunta que vai aparecer, e a coluna `destino` da etiqueta so
 * sabe responder pelo presente. A auditoria da casa registra a acao; esta
 * tabela guarda a linha do tempo.
 *
 * Os acessos sao agregados POR DIA, e nao uma linha por leitura. Uma placa em
 * porta de loja movimentada geraria dezenas de milhares de linhas por mes numa
 * hospedagem compartilhada, e a pergunta que o cliente faz e "quantas leituras
 * no mes", nunca "quem leu as 14h03".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinos_etiqueta', function (Blueprint $t) {
            $t->id();
            $t->foreignId('etiqueta_id')->constrained('etiquetas')->restrictOnDelete();
            $t->string('destino', 1000);

            // A linha com `vigorou_ate` nulo e a atual. So pode haver uma.
            $t->timestamp('vigorou_de');
            $t->timestamp('vigorou_ate')->nullable();

            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();

            $t->index(['etiqueta_id', 'vigorou_ate']);
        });

        Schema::create('renovacoes_etiqueta', function (Blueprint $t) {
            $t->id();
            $t->foreignId('etiqueta_id')->constrained('etiquetas')->restrictOnDelete();

            // O valor COBRADO, e nao o valor de tabela de hoje.
            $t->unsignedBigInteger('valor_cents');

            $t->date('de');
            $t->date('ate');
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();

            $t->index('etiqueta_id');
        });

        Schema::create('acessos_etiqueta', function (Blueprint $t) {
            $t->id();
            $t->foreignId('etiqueta_id')->constrained('etiquetas')->restrictOnDelete();
            $t->date('dia');
            $t->unsignedBigInteger('total')->default(0);

            // Sem timestamps de proposito: a linha e um contador somado a cada
            // leitura, e gravar `updated_at` em todo incremento so dobraria a
            // escrita da rota mais quente do sistema.

            // E o par que o upsert do contador usa como chave.
            $t->unique(['etiqueta_id', 'dia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos_etiqueta');
        Schema::dropIfExists('renovacoes_etiqueta');
        Schema::dropIfExists('destinos_etiqueta');
    }
};
