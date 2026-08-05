<?php

namespace App\Actions\Consumo;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Support\Comissao;
use App\Support\Margem;
use Illuminate\Support\Facades\DB;

/**
 * Fecha o mes de uma empresa e emite a fatura.
 *
 * Le as consultas da competencia, monta a cascata e grava tudo: total, imposto,
 * custo, lucro e comissao, mais a aliquota e o percentual usados. A fatura passa
 * a se explicar sozinha, sem depender do catalogo de hoje nem dos parametros de
 * hoje.
 *
 * O custo e a soma do que foi congelado consulta a consulta, e nao uma media do
 * catalogo: aqui o mix consumido e conhecido, entao nao ha por que estimar.
 */
class FecharCompetencia
{
    /** @return array{erro: string|null, fatura: Fatura|null} */
    public function __invoke(Cliente $cliente, string $competencia): array
    {
        if (! $cliente->plano) {
            return ['erro' => 'Empresa sem plano contratado.', 'fatura' => null];
        }

        if (Fatura::where('cliente_id', $cliente->id)->where('competencia', $competencia)->exists()) {
            return ['erro' => "A competencia {$competencia} ja esta fechada.", 'fatura' => null];
        }

        $plano = $cliente->plano;
        $catalogo = $plano->catalogo;

        $consultas = Consulta::where('cliente_id', $cliente->id)
            ->where('competencia', $competencia)
            ->get();

        $realizado = (int) $consultas->sum('preco_cents');
        $custo = (int) $consultas->sum('custo_cents');

        // O minimo e piso de cobranca: o cliente paga o maior entre o que
        // consumiu e o que contratou, mas so custa o que consultou de fato.
        $faturado = max($realizado, $plano->consumo_minimo_cents);
        $total = $plano->mensalidade_cents + $faturado;

        $impostoBps = $catalogo?->imposto_bps ?? 0;
        $imposto = Margem::impostoCents($total, $impostoBps);

        $lucro = $total - $imposto - $custo;
        $comissao = Comissao::cents($lucro);

        $fatura = DB::transaction(fn () => Fatura::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $cliente->vendedor_id,
            'competencia' => $competencia,
            'mensalidade_cents' => $plano->mensalidade_cents,
            'consumo_minimo_cents' => $plano->consumo_minimo_cents,
            'consumo_realizado_cents' => $realizado,
            'consumo_faturado_cents' => $faturado,
            'total_cents' => $total,
            'imposto_bps' => $impostoBps,
            'imposto_cents' => $imposto,
            'custo_cents' => $custo,
            'lucro_cents' => $lucro - $comissao,
            'comissao_pct' => Comissao::pct(),
            'comissao_cents' => $comissao,
            'fechada_em' => now(),
        ]));

        return ['erro' => null, 'fatura' => $fatura];
    }
}
