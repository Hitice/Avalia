<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O produtor passa a viver numa rede, e cada venda paga a linha inteira.
 *
 * `pai_id` e quem trouxe este parceiro. A cadeia de pais e o caminho que a
 * comissao percorre: quem vendeu recebe o dele, quem o trouxe recebe o dele, e
 * assim ate o topo. O provedor aceita varios beneficiarios na mesma cobranca,
 * entao a divisao acontece no pagamento e nao numa apuracao nossa depois.
 *
 * `percentual_bps` e o que ESTE parceiro recebe de uma venda feita por ele ou
 * por alguem abaixo dele, em pontos base: 3000 sao 30%. Guardado por parceiro,
 * e nao por nivel, porque negociacao de comissao e individual e muda sem
 * avisar o resto da rede.
 *
 * `profundidade` existe para evitar a consulta recursiva a cada venda e para
 * travar ciclo: ninguem pode ser o proprio avo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtores', function (Blueprint $t) {
            $t->foreignId('pai_id')->nullable()->after('id')->constrained('produtores')->nullOnDelete();
            $t->unsignedInteger('percentual_bps')->default(0)->after('situacao');
            $t->unsignedSmallInteger('profundidade')->default(0)->after('percentual_bps');

            $t->index(['pai_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::table('produtores', function (Blueprint $t) {
            $t->dropForeign(['pai_id']);
            $t->dropColumn(['pai_id', 'percentual_bps', 'profundidade']);
        });
    }
};
