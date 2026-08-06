<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consulta deixa de ser so uma linha de cobranca.
 *
 * Ate agora `consultas` guardava preco e custo, e nada do que foi perguntado
 * nem do que voltou. Faltavam as tres coisas que a secao 14 do PDD exige:
 * finalidade, responsavel e prazo de expurgo.
 *
 * `resposta` guarda o retorno do bureau e e apagada na retencao. O resto da
 * linha fica para sempre, porque e o que explica a cobranca: apagar a consulta
 * inteira deixaria uma fatura sem composicao.
 *
 * `documento` e dado pessoal e vive sob a mesma retencao da resposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $t) {
            // O que foi perguntado, e por que. Sem finalidade declarada nao ha
            // base legal que se sustente numa fiscalizacao.
            $t->string('documento', 20)->nullable()->after('servico_id');
            $t->string('finalidade', 120)->nullable()->after('documento');

            // Quem pediu. A consulta parte da empresa, mas a pessoa que clicou
            // e o responsavel, e e dela que se cobra explicacao.
            $t->string('solicitante', 150)->nullable()->after('finalidade');

            // Falha nao vira cobranca, mas vira registro: tentativa que nao
            // completou ainda e tentativa, e o cliente pode perguntar por que.
            $t->string('situacao', 20)->default('sucesso')->index()->after('solicitante');
            $t->string('referencia_externa', 120)->nullable()->after('situacao');
            $t->unsignedInteger('duracao_ms')->nullable()->after('referencia_externa');

            $t->json('resposta')->nullable()->after('duracao_ms');
            $t->timestamp('expurgada_em')->nullable()->after('resposta');
            $t->date('expurgar_em')->nullable()->index()->after('expurgada_em');
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $t) {
            $t->dropColumn([
                'documento', 'finalidade', 'solicitante', 'situacao',
                'referencia_externa', 'duracao_ms', 'resposta',
                'expurgada_em', 'expurgar_em',
            ]);
        });
    }
};
