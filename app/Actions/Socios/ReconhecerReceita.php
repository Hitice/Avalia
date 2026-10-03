<?php

namespace App\Actions\Socios;

use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Enums\NaturezaLancamento;
use App\Models\LancamentoFinanceiro;

/**
 * Receita com origem no sistema, uma vez por origem. A fatura do One tem a
 * sua propria (FaturaNoRazao, com imposto, custo e comissao); esta serve ao
 * que so tem o valor, e passa por Lancar como todo o resto.
 */
class ReconhecerReceita
{
    public function __construct(private Lancar $lancar) {}

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

        return $this->lancar->umaVez(Partidas::porCodigo(['caixa' => $valorCents, 'receita' => -$valorCents]), [
            'natureza' => NaturezaLancamento::Receita->value,
            'descricao' => $descricao,
            'competencia' => $quando->format('Y-m'),
            'ocorrido_em' => $quando->format('Y-m-d'),
            'origem_tipo' => $origemTipo,
            'origem_id' => $origemId,
        ]);
    }
}
