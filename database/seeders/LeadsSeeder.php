<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Seeder;

/**
 * Carga da base de prospeccao.
 *
 * So insere o que ainda nao existe, comparando pelo codigo da base de origem.
 * Lead ja gravado e cadastro que a operacao pode ter corrigido a mao (telefone
 * novo, e-mail que voltou), e reimportar o arquivo nao pode desfazer isso. E a
 * mesma regra do CustosSeeder.
 *
 * `insert` em lotes, e nao um `create` por linha: sao mais de mil registros
 * contra banco remoto, e um insert por linha estoura o tempo da publicacao.
 */
class LeadsSeeder extends Seeder
{
    /** Tamanho do lote de insert. Acima disso o Postgres reclama do numero de parametros. */
    private const LOTE = 200;

    public function run(): void
    {
        $base = require database_path('seeders/dados/leads_base_2026_08.php');

        // withTrashed: lead removido pela tela nao volta pela carga. A remocao
        // foi uma decisao, e a carga nao desfaz decisao.
        $jaGravados = Lead::withTrashed()->whereNotNull('codigo')->pluck('codigo')->all();
        $existentes = array_flip($jaGravados);

        $novos = [];
        $agora = now();

        foreach ($base as $lead) {
            if ($lead['codigo'] !== null && isset($existentes[$lead['codigo']])) {
                continue;
            }

            $novos[] = $lead + ['created_at' => $agora, 'updated_at' => $agora];
        }

        if ($novos === []) {
            $this->command?->info('Base de leads já carregada: nada a inserir.');

            return;
        }

        foreach (array_chunk($novos, self::LOTE) as $lote) {
            Lead::insert($lote);
        }

        $this->command?->info(count($novos).' leads inseridos na base.');
    }
}
