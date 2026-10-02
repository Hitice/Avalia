<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O estoque pessoal de cada vendedor, e o link de cadastro dele.
 *
 * Maria pega 20 placas e presta conta delas. Antes nao havia onde registrar isso:
 * `vendedor_id` so era escrito na VENDA, entao entre a entrega e a venda a placa
 * nao era de ninguem, e ninguem sabia quantas cada um tinha na mao.
 *
 * O estoque NAO e um contador. E a consulta: consignadas a ela e ainda nao
 * vendidas. Contador precisa ser decrementado por quem vende, e o dia em que a
 * venda acontecer por outro caminho ele fica errado sem ninguem notar. A casa ja
 * aplica isso no razao, onde saldo e a soma das partidas.
 *
 * Consignar nao esconde o bolo comum: placa sem dono continua visivel a toda a
 * equipe, pela razao que a PDD registra (placa parada porque o vendedor dela esta
 * em campo custa mais que o risco). O que muda e que agora existe o recorte
 * pessoal ao lado do comum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiquetas', function (Blueprint $t) {
            // Com quem a placa esta, que e diferente de quem vendeu e de quem
            // gerou o lote. Tres perguntas, tres colunas.
            $t->foreignId('consignada_para_id')->nullable()->after('vendedor_id')
                ->constrained('staff')->nullOnDelete();

            $t->timestamp('consignada_em')->nullable()->after('consignada_para_id');

            $t->index(['consignada_para_id', 'vendida_em']);
        });

        Schema::table('negocios', function (Blueprint $t) {
            // Quem trouxe o cliente, pelo link dele. Nulo quando o cadastro veio
            // por outro caminho.
            $t->foreignId('vendedor_id')->nullable()->after('lead_id')
                ->constrained('staff')->nullOnDelete();
        });

        Schema::table('staff', function (Blueprint $t) {
            // O codigo do link publico de cadastro. Sorteado e nao sequencial:
            // com o id na URL, trocar um numero atribuiria o cliente a outro
            // vendedor.
            $t->string('codigo_indicacao', 8)->nullable()->unique()->after('comissao_pct');
        });

        // Quem ja existe recebe o codigo agora, para o link funcionar sem que
        // alguem precise reeditar cada conta.
        foreach (DB::table('staff')->whereNull('codigo_indicacao')->pluck('id') as $id) {
            DB::table('staff')->where('id', $id)->update([
                'codigo_indicacao' => strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 8)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('etiquetas', function (Blueprint $t) {
            $t->dropIndex(['consignada_para_id', 'vendida_em']);
            $t->dropConstrainedForeignId('consignada_para_id');
            $t->dropColumn('consignada_em');
        });

        Schema::table('negocios', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vendedor_id');
        });

        Schema::table('staff', function (Blueprint $t) {
            $t->dropColumn('codigo_indicacao');
        });
    }
};
