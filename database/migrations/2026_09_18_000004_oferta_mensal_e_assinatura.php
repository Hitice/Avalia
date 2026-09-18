<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço cobrado todo mes, e nao venda dividida em parcelas.
 *
 * Sao coisas diferentes e o sistema precisava saber disso. No parcelamento o
 * valor total existe desde o comeco e se divide; na mensalidade nao ha total,
 * ha uma cobranca que se repete enquanto o servico durar. Quem vende defesa de
 * desconto indevido cobra por mes ate o processo acabar, e nao ha "doze vezes
 * de" nenhuma.
 *
 * `tipo` na oferta decide o caminho: `parcelada` mantem o carne com entrada,
 * `mensal` cria uma assinatura no provedor. O provedor gera as cobrancas uma a
 * uma ao longo do tempo e aplica o mesmo split em todas, entao a rede continua
 * recebendo na recorrencia sem apuracao nossa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofertas_360', function (Blueprint $t) {
            $t->string('tipo', 20)->default('parcelada')->after('titulo');

            // Ate quando cobrar. Nulo e "enquanto o servico durar", que e o
            // caso do processo sem data para acabar.
            $t->unsignedSmallInteger('meses')->nullable()->after('parcelas');
        });

        Schema::table('pedidos_360', function (Blueprint $t) {
            $t->string('asaas_subscription_id', 60)->nullable()->after('asaas_customer_id');
        });

        Schema::table('parcelas_360', function (Blueprint $t) {
            // Na mensalidade a competencia importa mais que o numero: "a de
            // marco" e como cliente e vendedor falam da cobranca.
            $t->string('competencia', 7)->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('ofertas_360', fn (Blueprint $t) => $t->dropColumn(['tipo', 'meses']));
        Schema::table('pedidos_360', fn (Blueprint $t) => $t->dropColumn('asaas_subscription_id'));
        Schema::table('parcelas_360', fn (Blueprint $t) => $t->dropColumn('competencia'));
    }
};
