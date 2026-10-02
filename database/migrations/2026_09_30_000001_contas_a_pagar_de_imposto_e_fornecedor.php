<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As duas contrapartidas que faltavam para a fatura lancar por competencia.
 *
 * Fatura fechada reconhece receita, custo, imposto e comissao no mes em que o
 * consumo aconteceu, mas nada disso foi PAGO ainda. Sem conta de passivo, a
 * unica contrapartida possivel seria o caixa, e o razao diria que o dinheiro
 * saiu num dia em que ele nao saiu.
 *
 * Comissao ja tinha a dela (`comissao-a-pagar`). Estas sao as irmas.
 */
return new class extends Migration
{
    private const CONTAS = [
        ['codigo' => 'imposto-a-pagar', 'nome' => 'Imposto a recolher', 'grupo' => 'passivo'],
        ['codigo' => 'fornecedores-a-pagar', 'nome' => 'Fornecedores a pagar', 'grupo' => 'passivo'],
    ];

    public function up(): void
    {
        $agora = now();

        foreach (self::CONTAS as $conta) {
            if (DB::table('contas_financeiras')->where('codigo', $conta['codigo'])->exists()) {
                continue;
            }

            DB::table('contas_financeiras')->insert($conta + [
                'ativa' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    /** Sem volta: apagar conta levaria junto as partidas que apontam para ela. */
    public function down(): void {}
};
