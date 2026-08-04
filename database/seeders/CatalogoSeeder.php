<?php

namespace Database\Seeders;

use App\Models\Preco;
use App\Models\Servico;
use App\Models\VersaoCatalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carrega a tabela de referencia do fornecedor no catalogo.
 *
 * Nao e dado de exemplo: sao os precos reais transcritos dos PDFs de temp/,
 * ponto de partida para a administracao ajustar na tela.
 *
 * Idempotente: rodar de novo atualiza os precos em vez de duplicar. Cuidado —
 * isso sobrescreve ajuste feito a mao, entao nao rode depois de reajustar.
 */
class CatalogoSeeder extends Seeder
{
    private const ROTULO = 'Tabela de referência 04/2026';

    public function run(): void
    {
        $dados = require database_path('seeders/dados/precos_referencia_2026_04.php');

        $versao = VersaoCatalogo::firstOrCreate(
            ['rotulo' => self::ROTULO],
            [
                'observacao' => 'Transcrita dos PDFs de referencia por '
                    .'tools/gera_precos_catalogo.py. Homologar preco, custo e franquia.',
            ],
        );

        DB::transaction(function () use ($versao, $dados) {
            // Grava em lote, nao linha a linha. Sao 43 servicos e 301 precos:
            // contra um Postgres remoto, um save() por preco vira ~600 idas e
            // voltas pela internet e o seeder nao termina.
            //
            // `ativo` fica de fora da lista de atualizacao de proposito: se a
            // administracao desligou um servico, reimportar a tabela do
            // fornecedor nao pode religa-lo.
            Servico::upsert(
                array_map(fn (array $linha) => [
                    'codigo' => $linha['codigo'],
                    'nome' => $linha['nome'],
                    'categoria' => $linha['categoria'],
                    'exige_liberacao' => $linha['exige_liberacao'],
                ], $dados['servicos']),
                ['codigo'],
                ['nome', 'categoria', 'exige_liberacao'],
            );

            $idPorCodigo = Servico::whereIn('codigo', array_column($dados['servicos'], 'codigo'))
                ->pluck('id', 'codigo');

            $precos = [];

            foreach ($dados['servicos'] as $linha) {
                foreach ($dados['faixas'] as $indice => $faixaCents) {
                    // Custo do fornecedor fica nulo: a tabela transcrita traz
                    // preco de venda, e o custo vem do contrato, em separado.
                    $precos[] = [
                        'versao_id' => $versao->id,
                        'servico_id' => $idPorCodigo[$linha['codigo']],
                        'consumo_minimo_cents' => $faixaCents,
                        'preco_cents' => $linha['precos'][$indice],
                    ];
                }
            }

            Preco::upsert(
                $precos,
                ['versao_id', 'servico_id', 'consumo_minimo_cents'],
                ['preco_cents'],
            );
        });

        $this->command->info(sprintf(
            "Catalogo '%s' em rascunho: %d servicos, %d precos. Ative apos homologar.",
            $versao->rotulo,
            count($dados['servicos']),
            $versao->precos()->count(),
        ));
    }
}
