<?php

namespace Database\Seeders;

use App\Models\Plano;
use App\Models\VersaoCatalogo;
use App\Support\Dinheiro;
use Illuminate\Database\Seeder;

/**
 * Um plano por faixa da versao vigente.
 *
 * O produto do fornecedor e literalmente "mensalidade + consumo minimo a
 * escolher, e o preco da consulta segue a coluna escolhida" — entao a grade
 * inicial da Avalia e uma linha por faixa, com a mensalidade propria.
 *
 * Franquia fica zerada: quantas consultas de cada servico entram na
 * mensalidade e decisao comercial ainda pendente (PDD.md, secao 16). Zero nao
 * quebra nada — significa que toda consulta e excedente, e o piso do mes
 * continua sendo o consumo minimo.
 *
 * Idempotente: rodar de novo nao duplica nem sobrescreve plano ja ajustado a
 * mao.
 */
class PlanosSeeder extends Seeder
{
    /** PDD.md, secao 5. Nao e o R$ 49,00 historico do fornecedor. */
    private const MENSALIDADE_CENTS = 7_990;

    public function run(): void
    {
        $versao = VersaoCatalogo::vigente() ?? VersaoCatalogo::latest('id')->first();

        if (! $versao) {
            $this->command->warn('Nenhuma versao de catalogo: rode o CatalogoSeeder antes.');

            return;
        }

        $faixas = $versao->faixas();

        if ($faixas === []) {
            $this->command->warn("Versao '{$versao->rotulo}' nao tem preco: nenhum plano criado.");

            return;
        }

        $criados = 0;

        foreach ($faixas as $faixaCents) {
            $plano = Plano::firstOrCreate(
                ['nome' => $this->nome($faixaCents)],
                [
                    'versao_id' => $versao->id,
                    'descricao' => 'Mensalidade fixa mais o consumo minimo da faixa. '
                        .'O preco de cada consulta segue a coluna correspondente do catalogo.',
                    'mensalidade_cents' => self::MENSALIDADE_CENTS,
                    'consumo_minimo_cents' => $faixaCents,
                    'ativo' => true,
                ],
            );

            $criados += (int) $plano->wasRecentlyCreated;
        }

        $this->command->info(sprintf(
            '%d planos criados, %d ja existiam, sobre a versao %s. Falta definir a franquia.',
            $criados,
            count($faixas) - $criados,
            $versao->rotulo,
        ));
    }

    private function nome(int $faixaCents): string
    {
        return $faixaCents === 0
            ? 'Sem consumo mínimo'
            : 'Consumo mínimo R$ '.Dinheiro::numero($faixaCents);
    }
}
