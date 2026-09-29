<?php

namespace App\Actions\Socios;

use App\Exceptions\Recusa;
use App\Models\LancamentoFinanceiro;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz um lancamento lancando o contrario.
 *
 * Nao edita e nao apaga. Saldo que alguem ja conferiu nao pode mudar por
 * edicao: o extrato de ontem tem de continuar explicando o numero de ontem, e o
 * conserto aparece como um evento proprio, com data e autor.
 *
 * As pernas sao as mesmas com o sinal trocado, entao o estorno fecha em zero
 * pela mesma razao que o original fechava, e a soma das duas linhas devolve o
 * saldo ao ponto de partida.
 *
 * Estorno de estorno nao existe: o lancamento ja desfeito recusa. Duas
 * correcoes empilhadas sobre o mesmo fato viram saldo que ninguem reconstroi.
 */
class EstornarLancamento
{
    public function __invoke(LancamentoFinanceiro $lancamento, string $motivo): LancamentoFinanceiro
    {
        if ($lancamento->estorna_id !== null) {
            throw new Recusa('Este lançamento já é um estorno. Para corrigir, registre um novo lançamento.');
        }

        if ($lancamento->estornado()) {
            throw new Recusa('Este lançamento já foi estornado.');
        }

        if (trim($motivo) === '') {
            throw new Recusa('Diga o motivo do estorno: ele fica no extrato ao lado do valor.');
        }

        return DB::transaction(function () use ($lancamento, $motivo) {
            $estorno = LancamentoFinanceiro::create([
                'natureza' => $lancamento->natureza->value,
                'descricao' => 'Estorno: '.trim($motivo),

                // A competencia e a de HOJE, e nao a do original: o mes fechado
                // continua com o numero que teve, e a correcao pertence ao mes
                // em que foi decidida. Reabrir competencia para arrumar o
                // passado e o que faz um numero conferido mudar sozinho.
                'competencia' => now()->format('Y-m'),
                'ocorrido_em' => now()->toDateString(),

                'contraparte' => $lancamento->contraparte,
                'documento' => $lancamento->documento,
                'staff_id' => auth('staff')->id(),
                'estorna_id' => $lancamento->id,
            ]);

            foreach ($lancamento->partidas as $partida) {
                $estorno->partidas()->create([
                    'conta_id' => $partida->conta_id,
                    'valor_cents' => -$partida->valor_cents,
                ]);
            }

            Auditar::registrar('socios.lancamento.estornado', $estorno, [
                'estorna' => $lancamento->id,
                'motivo' => trim($motivo),
            ]);

            return $estorno->load('partidas');
        });
    }
}
