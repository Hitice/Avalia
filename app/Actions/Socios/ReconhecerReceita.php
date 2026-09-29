<?php

namespace App\Actions\Socios;

use App\Enums\NaturezaLancamento;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use Illuminate\Database\QueryException;

/**
 * Leva ao caixa dos socios o dinheiro que um produto trouxe.
 *
 * Ate aqui o razao dos socios so conhecia o que alguem digitasse, e por isso o
 * caixa ignorava a receita do proprio negocio. A ligacao e de UMA direcao:
 * financeiro alimenta socios, e nunca o contrario. Aporte e retirada nao tem o
 * que fazer nas telas de cliente.
 *
 * **Nao duplica, por construcao.** O par (`origem_tipo`, `origem_id`) e unico
 * no banco, entao a mesma fatura nao vira receita duas vezes nem que a rotina
 * rode de novo, o webhook chegue repetido ou alguem reprocesse a competencia. A
 * colisao e capturada e tratada como sucesso, porque reconhecer de novo o que
 * ja foi reconhecido nao e erro: e a segunda tentativa encontrando o trabalho
 * feito.
 *
 * A conta de receita nasce sob demanda. Depender de seeder foi o que subiu o
 * modulo inutilizavel em 29/09/2026, e liquidacao de fatura nao pode falhar
 * porque uma linha do plano de contas nao existe.
 */
class ReconhecerReceita
{
    public function __invoke(
        string $origemTipo,
        int $origemId,
        int $valorCents,
        string $descricao,
        \DateTimeInterface $quando,
    ): ?LancamentoFinanceiro {
        if ($valorCents <= 0) {
            return null;
        }

        $caixa = $this->conta('caixa', 'Caixa', 'ativo');
        $receita = $this->conta('receita', 'Receita', 'receita');

        try {
            $lancamento = LancamentoFinanceiro::create([
                'natureza' => NaturezaLancamento::Receita->value,
                'descricao' => $descricao,
                'competencia' => $quando->format('Y-m'),
                'ocorrido_em' => $quando->format('Y-m-d'),
                'origem_tipo' => $origemTipo,
                'origem_id' => $origemId,
                'staff_id' => auth('staff')->id(),
            ]);
        } catch (QueryException $e) {
            // Chave unica de origem: ja reconhecido. Ver o docblock.
            return null;
        }

        $lancamento->partidas()->create(['conta_id' => $caixa, 'valor_cents' => $valorCents]);
        $lancamento->partidas()->create(['conta_id' => $receita, 'valor_cents' => -$valorCents]);

        return $lancamento;
    }

    private function conta(string $codigo, string $nome, string $grupo): int
    {
        return ContaFinanceira::firstOrCreate(
            ['codigo' => $codigo],
            ['nome' => $nome, 'grupo' => $grupo],
        )->id;
    }
}
