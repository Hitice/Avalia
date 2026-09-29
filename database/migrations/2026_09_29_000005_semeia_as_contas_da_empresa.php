<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As tres contas sem as quais o razao nao aceita um lancamento sequer.
 *
 * Vem por migration, e nao por seeder, porque nao e dado de exemplo: e parte da
 * estrutura. `RegistrarLancamento` procura a conta pelo codigo e recusa quando
 * nao acha, entao o modulo subiu em producao inutilizavel, respondendo "A conta
 * caixa nao esta cadastrada" a qualquer tentativa.
 *
 * Seeder nao resolveria sozinho pela regra que esta casa ja conhece: passo novo
 * acrescentado ao `deploy.sh` nao roda na publicacao que traz esse mesmo
 * `deploy.sh`. Migration roda na hora, e roda uma vez.
 *
 * As contas dos socios continuam nascendo sob demanda, no primeiro lancamento
 * de cada um: aquelas dependem de quem existe, e estas nao dependem de nada.
 */
return new class extends Migration
{
    private const CONTAS = [
        ['codigo' => 'caixa', 'nome' => 'Caixa', 'grupo' => 'ativo'],
        ['codigo' => 'receita', 'nome' => 'Receita', 'grupo' => 'receita'],
        ['codigo' => 'despesa', 'nome' => 'Despesa', 'grupo' => 'despesa'],
    ];

    public function up(): void
    {
        $agora = now();

        foreach (self::CONTAS as $conta) {
            // Guardado por existencia: republicar nao duplica conta nem desfaz
            // um nome corrigido na tela.
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

    /**
     * Sem volta.
     *
     * Apagar a conta levaria junto as partidas que apontam para ela, e o razao
     * deixaria de explicar saldo nenhum. A chave estrangeira das partidas ja
     * recusa a exclusao; isto aqui apenas nao tenta.
     */
    public function down(): void {}
};
