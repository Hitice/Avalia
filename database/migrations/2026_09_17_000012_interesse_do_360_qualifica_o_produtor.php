<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O formulario do 360 passa a qualificar em vez de cadastrar.
 *
 * Quem chega pela pagina ainda esta decidindo, e as perguntas mudaram de
 * natureza: em vez de documento e ticket medio, que sao dados de cadastro,
 * agora se pergunta o que a pessoa vende, qual o papel dela no negocio, quando
 * quer comecar e quanto faturou. Sao as respostas que dizem se vale ligar hoje
 * ou daqui a um mes.
 *
 * `documento` e `ticket_medio_cents` passam a aceitar nulo em vez de sumir: os
 * pedidos ja gravados continuam explicaveis, e quem preencheu o formulario
 * antigo nao vira linha pela metade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessados_cobranca', function (Blueprint $t) {
            $t->string('instagram', 60)->nullable()->after('whatsapp');
            $t->string('vende', 60)->nullable()->after('instagram');
            $t->string('papel', 60)->nullable()->after('vende');
            $t->string('prazo', 40)->nullable()->after('papel');
            $t->string('faturamento_ano', 40)->nullable()->after('prazo');
        });

        // Em SQLite o `change()` recria a tabela; fazer os dois numa chamada so
        // evita duas recriacoes seguidas.
        Schema::table('interessados_cobranca', function (Blueprint $t) {
            $t->text('documento')->nullable()->change();
            $t->unsignedBigInteger('ticket_medio_cents')->nullable()->change();
            $t->string('volume_mensal', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('interessados_cobranca', function (Blueprint $t) {
            $t->dropColumn(['instagram', 'vende', 'papel', 'prazo', 'faturamento_ano']);
        });
    }
};
