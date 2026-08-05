<?php

namespace Database\Seeders;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Models\Servico;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial do custo do fornecedor.
 *
 * O mesmo custo vai para todas as faixas do servico: o fornecedor cobra por
 * consulta, nao por faixa contratada pelo cliente final.
 *
 * So preenche o que esta vazio. Custo ja cadastrado e ajuste da administracao
 * feito na tela, e reimportar a tabela do fornecedor nao pode desfazer isso,
 * mesma regra que ja vale para `ativo` e `exige_liberacao`.
 */
class CustosSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = Catalogo::vigente();

        if (! $catalogo) {
            $this->command->warn('Nenhum catalogo: rode o CatalogoSeeder antes.');

            return;
        }

        $custos = require database_path('seeders/dados/custos_fornecedor_2026_04.php');
        $idPorCodigo = Servico::whereIn('codigo', array_keys($custos))->pluck('id', 'codigo');

        $ausentes = array_diff(array_keys($custos), $idPorCodigo->keys()->all());

        if ($ausentes !== []) {
            $this->command->warn('Sem servico correspondente: '.implode(', ', $ausentes));
        }

        $preenchidos = 0;
        $jaTinham = 0;

        DB::transaction(function () use ($catalogo, $custos, $idPorCodigo, &$preenchidos, &$jaTinham) {
            foreach ($custos as $codigo => $custoCents) {
                if (! $idPorCodigo->has($codigo)) {
                    continue;
                }

                $linhas = $catalogo->precos()
                    ->where('servico_id', $idPorCodigo[$codigo])
                    ->get(['id', 'catalogo_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents']);

                $jaTinham += $linhas->whereNotNull('custo_cents')->count();

                $novas = $linhas
                    ->whereNull('custo_cents')
                    ->map(fn (Preco $preco) => [
                        'id' => $preco->id,
                        'catalogo_id' => $preco->catalogo_id,
                        'servico_id' => $preco->servico_id,
                        'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                        'preco_cents' => $preco->preco_cents,
                        'custo_cents' => $custoCents,
                    ])
                    ->values()
                    ->all();

                if ($novas !== []) {
                    Preco::upsert($novas, ['id'], ['custo_cents']);
                    $preenchidos += count($novas);
                }
            }
        });

        $semCusto = $catalogo->precos()->whereNull('custo_cents')->count();

        $this->command->info(sprintf(
            '%d preco(s) receberam custo, %d ja tinham. Seguem sem custo: %d.',
            $preenchidos,
            $jaTinham,
            $semCusto,
        ));
    }
}
