<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plaquinha de QR e NFC, e a tiragem que a produziu.
 *
 * A etiqueta nasce em branco, no estoque, e so ganha destino depois de
 * vendida. E por isso que quase tudo aqui e opcional: o que existe no momento
 * em que a placa e cortada e um codigo e um numero de ordem.
 *
 * O `codigo` e a unica coluna que nao pode mudar nunca. Ele esta gravado em
 * acrilico no balcao de uma loja, e o indice unico e o que garante que dois
 * clientes nunca disputem o mesmo endereco.
 *
 * `valor_cents` fica gravado na venda, e nao lido do config na hora de
 * mostrar: reajuste de hoje nao pode reescrever o que foi cobrado ontem, que e
 * a mesma regra da consulta e da fatura.
 *
 * Cada `create` e guardado por `hasTable`. Publicacao que roda duas vezes ao
 * mesmo tempo, ou que morre no meio, deixa a tabela criada sem a migration
 * registrada: dai em diante toda publicacao falha em "table already exists" e
 * volta atras sozinha. Ja aconteceu nesta base, com coluna em vez de tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('lotes_etiquetas') || Schema::create('lotes_etiquetas', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 20)->unique();
            $t->string('titulo', 120);
            $t->unsignedInteger('quantidade');
            $t->string('tipo', 10)->default('qr');
            $t->string('observacao', 255)->nullable();
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();
        });

        Schema::hasTable('etiquetas') || Schema::create('etiquetas', function (Blueprint $t) {
            $t->id();

            // Seis caracteres Crockford. Guardado em maiusculo; quem procura
            // normaliza antes, porque ninguem digita igual ao impresso.
            $t->string('codigo', 6)->unique();

            // Nula na etiqueta avulsa, que nasce sem placa e sem tiragem.
            $t->foreignId('lote_id')->nullable()->constrained('lotes_etiquetas')->restrictOnDelete();
            $t->unsignedInteger('sequencia')->nullable();

            $t->string('tipo', 10)->default('qr');
            $t->string('situacao', 15)->default('em_branco');

            // Limite generoso, mas limite: URL sem teto vira campo onde alguem
            // cola um texto inteiro, e o redirecionamento passa a mandar lixo.
            $t->string('destino', 1000)->nullable();

            // Apelido interno, para achar a placa na lista sem decorar codigo.
            $t->string('titulo', 120)->nullable();
            $t->string('cliente_nome', 150)->nullable();
            $t->string('cliente_contato', 150)->nullable();

            $t->timestamp('vendida_em')->nullable();
            $t->date('vence_em')->nullable();

            // Marca do aviso de vencimento ja enviado. Sem ela, cron repetido
            // manda o mesmo e-mail todo dia, que e o jeito mais rapido de o
            // cliente criar regra de spam para a gente.
            $t->timestamp('avisada_em')->nullable();

            $t->unsignedBigInteger('valor_cents')->nullable();

            // Quando a tag NFC desta plaquinha foi gravada na bancada.
            $t->timestamp('gravada_em')->nullable();

            // So no avulso mensal, que e o unico cobrado por recorrencia.
            $t->string('asaas_subscription_id', 60)->nullable();

            // Copias do agregado de acessos, para a listagem nao somar nada.
            $t->unsignedBigInteger('total_acessos')->default(0);
            $t->timestamp('ultimo_acesso_em')->nullable();

            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();

            // Duas placas com o mesmo numero na mesma tiragem seriam dois
            // arquivos com o mesmo nome dentro do ZIP.
            $t->unique(['lote_id', 'sequencia']);
            $t->index('situacao');

            // O comando diario de aviso varre por esta coluna.
            $t->index('vence_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiquetas');
        Schema::dropIfExists('lotes_etiquetas');
    }
};
