<?php

namespace Database\Seeders;

use App\Models\Preco;
use App\Models\Servico;
use App\Models\VersaoCatalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carrega a tabela de referencia do fornecedor como RASCUNHO.
 *
 * Nao e dado de exemplo: sao os precos reais transcritos dos PDFs de temp/.
 * Entram como rascunho de proposito — o PDD exige homologacao comercial antes
 * da ativacao, e uma versao ativa e imutavel. Quem ativa e a administracao,
 * depois de conferir.
 *
 * Idempotente: rodar de novo atualiza a versao em rascunho em vez de duplicar.
 * Se a versao ja tiver sido ativada, o seeder nao toca em nada.
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
                'situacao' => 'rascunho',
                'observacao' => 'Transcrita de temp/*.pdf por tools/gera_precos_catalogo.py. '
                    .'Homologar preco, custo e franquia antes de ativar.',
            ],
        );

        if ($versao->estaCongelada()) {
            $this->command->warn(
                "Versao '{$versao->rotulo}' ja esta {$versao->situacao}: nada alterado."
            );

            return;
        }

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

            // upsert nao dispara evento de model, entao a guarda de
            // congelamento do Preco nao roda aqui — e por isso que o
            // estaCongelada() acima e obrigatorio, e nao mera cortesia.
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
