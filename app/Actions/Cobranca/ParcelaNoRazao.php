<?php

namespace App\Actions\Cobranca;

use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Models\LancamentoFinanceiro;
use App\Models\Parcela360;

/**
 * A parcela paga do Gestor no razao da casa. So a parte da plataforma e
 * nossa: o bruto, a taxa do provedor e o repasse sao do produtor e ficam no
 * razao dele (lancamentos_360). Sem isto a receita do Gestor nao existia no
 * resultado da casa.
 */
class ParcelaNoRazao
{
    public function __construct(private Lancar $lancar) {}

    public function registrar(Parcela360 $parcela, int $casaCents, \DateTimeInterface $quando, string $descricao): ?LancamentoFinanceiro
    {
        if ($casaCents <= 0) {
            return null;
        }

        return $this->lancar->umaVez(Partidas::porCodigo(['caixa' => $casaCents, 'receita:gestor' => -$casaCents]), [
            'natureza' => 'receita',
            'descricao' => $descricao,
            'competencia' => $quando->format('Y-m'),
            'ocorrido_em' => $quando->format('Y-m-d'),
            'origem_tipo' => 'parcela360',
            'origem_id' => $parcela->id,
        ]);
    }
}
