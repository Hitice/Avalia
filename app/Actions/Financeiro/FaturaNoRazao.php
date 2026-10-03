<?php

namespace App\Actions\Financeiro;

use App\Actions\Socios\EstornarLancamento;
use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Models\Fatura;
use App\Models\LancamentoFinanceiro;

/**
 * A fatura liquidada no razao: o dinheiro que entrou, a receita, e as tres
 * dividas que nascem com ela (imposto, fornecedor, comissao). Tudo das
 * colunas congeladas na emissao. Antes so a receita entrava, e o mes fechava
 * sem saber quanto devia.
 */
class FaturaNoRazao
{
    public function __construct(private Lancar $lancar, private EstornarLancamento $estornar) {}

    public function partidas(Fatura $fatura): Partidas
    {
        $comissao = $fatura->comissao_liberada_em ? (int) $fatura->comissao_cents : 0;

        return Partidas::porCodigo([
            'caixa' => (int) $fatura->total_cents,
            'receita:one' => -(int) $fatura->total_cents,
            'imposto' => (int) $fatura->imposto_cents,
            'imposto-a-pagar' => -(int) $fatura->imposto_cents,
            'custo:one' => (int) $fatura->custo_cents,
            'fornecedores-a-pagar' => -(int) $fatura->custo_cents,
            'comissao' => $comissao,
            'comissao-a-pagar' => -$comissao,
        ]);
    }

    /** As pernas que nao sao dinheiro, para completar fatura lancada so com a receita. */
    public function complemento(Fatura $fatura): Partidas
    {
        $comissao = $fatura->comissao_liberada_em ? (int) $fatura->comissao_cents : 0;

        return Partidas::porCodigo([
            'imposto' => (int) $fatura->imposto_cents,
            'imposto-a-pagar' => -(int) $fatura->imposto_cents,
            'custo:one' => (int) $fatura->custo_cents,
            'fornecedores-a-pagar' => -(int) $fatura->custo_cents,
            'comissao' => $comissao,
            'comissao-a-pagar' => -$comissao,
        ]);
    }

    /**
     * Lanca a liquidacao. Fatura estornada e liquidada de novo lanca de novo:
     * a origem ganha um sufixo, porque o original estornado continua dono do
     * par (fatura, id), e o indice unico nao deixaria a segunda vez entrar.
     */
    public function registrar(Fatura $fatura, \DateTimeInterface $quando): ?LancamentoFinanceiro
    {
        $anteriores = self::lancamentosDe($fatura);

        if ($anteriores->contains(fn (LancamentoFinanceiro $l) => ! $l->estornado())) {
            return null;
        }

        return $this->lancar->umaVez($this->partidas($fatura), [
            'natureza' => 'receita',
            'descricao' => 'Fatura '.$fatura->competencia.' de '.($fatura->cliente?->razao_social ?? 'cliente'),
            'competencia' => $quando->format('Y-m'),
            'ocorrido_em' => $quando->format('Y-m-d'),
            'origem_tipo' => $anteriores->isEmpty() ? 'fatura' : 'fatura:'.$anteriores->count(),
            'origem_id' => $fatura->id,
        ]);
    }

    public function estornar(Fatura $fatura, string $motivo): void
    {
        foreach (self::lancamentosDe($fatura) as $lancamento) {
            if (! $lancamento->estornado()) {
                ($this->estornar)($lancamento, $motivo);
            }
        }
    }

    public static function lancamentosDe(Fatura $fatura)
    {
        return LancamentoFinanceiro::where('origem_id', $fatura->id)
            ->where(fn ($q) => $q->where('origem_tipo', 'fatura')->orWhere('origem_tipo', 'like', 'fatura:%'))
            ->whereNull('estorna_id')->with('estornos')->get();
    }
}
