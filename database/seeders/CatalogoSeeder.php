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
    private const ROTULO = 'Referência Bancredi 04/2026';

    public function run(): void
    {
        $dados = require database_path('seeders/dados/precos_bancredi_2026_04.php');

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
            foreach ($dados['servicos'] as $linha) {
                $servico = Servico::updateOrCreate(
                    ['codigo' => $linha['codigo']],
                    [
                        'nome' => $linha['nome'],
                        'categoria' => $linha['categoria'],
                        'exige_liberacao' => $linha['exige_liberacao'],
                    ],
                );

                foreach ($dados['faixas'] as $indice => $faixaCents) {
                    $preco = Preco::firstOrNew([
                        'versao_id' => $versao->id,
                        'servico_id' => $servico->id,
                        'consumo_minimo_cents' => $faixaCents,
                    ]);

                    // Custo do fornecedor fica nulo: a tabela transcrita traz
                    // preco de venda, e o custo vem do contrato, em separado.
                    $preco->preco_cents = $linha['precos'][$indice];

                    // A guarda de congelamento ja consultou a versao acima.
                    $preco->setRelation('versao', $versao);
                    $preco->save();
                }
            }
        });

        $this->command->info(sprintf(
            "Catalogo '%s' em rascunho: %d servicos, %d precos. Ative apos homologar.",
            $versao->rotulo,
            count($dados['servicos']),
            $versao->precos()->count(),
        ));
    }
}
