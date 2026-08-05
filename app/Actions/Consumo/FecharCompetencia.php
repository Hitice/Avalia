<?php

namespace App\Actions\Consumo;

use App\Actions\Financeiro\CriarCobrancaAsaas;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Support\Auditar;
use App\Support\Comissao;
use App\Support\Margem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $franquias = $plano->franquias()->pluck('quantidade', 'servico_id');
        $itens = [];
        $bruto = 0;
        $franquia = 0;
        $excedente = 0;

        // A franquia é aplicada serviço a serviço e em quantidade. Fazê-la
        // sobre a soma em reais permitiria que um serviço barato cobrisse um
        // caro, contrariando o que foi contratado.
        foreach ($consultas->groupBy('servico_id') as $servicoId => $grupo) {
            $grupo = $grupo->sortBy('id')->values();
            $quantidade = $grupo->count();
            $incluidas = min($quantidade, (int) ($franquias[$servicoId] ?? 0));
            $valorBruto = (int) $grupo->sum('preco_cents');
            $valorFranquia = (int) $grupo->take($incluidas)->sum('preco_cents');
            $valorExcedente = (int) $grupo->skip($incluidas)->sum('preco_cents');

            $bruto += $valorBruto;
            $franquia += $valorFranquia;
            $excedente += $valorExcedente;
            $itens[] = [
                'servico_id' => $servicoId,
                'servico_nome' => $grupo->first()->servico?->nome ?? 'Serviço',
                'quantidade' => $quantidade,
                'quantidade_franquia' => $incluidas,
                'quantidade_excedente' => $quantidade - $incluidas,
                'valor_bruto_cents' => $valorBruto,
                'valor_franquia_cents' => $valorFranquia,
                'valor_excedente_cents' => $valorExcedente,
                'custo_cents' => (int) $grupo->sum('custo_cents'),
            ];
        }

        $realizado = $excedente;
        $custo = (int) $consultas->sum('custo_cents');

        // O minimo e piso de cobranca: o cliente paga o maior entre o que
        // consumiu e o que contratou, mas so custa o que consultou de fato.
        $faturado = max($realizado, $plano->consumo_minimo_cents);
        $total = $plano->mensalidade_cents + $faturado;

        $impostoBps = $catalogo?->imposto_bps ?? 0;
        $imposto = Margem::impostoCents($total, $impostoBps);

        $lucro = $total - $imposto - $custo;

        // A taxa e a do vendedor da carteira, e nao a do sistema: a
        // administracao negocia caso a caso. Ela e congelada na fatura logo
        // abaixo, entao renegociar amanha nao reescreve competencia fechada.
        $pct = Comissao::pct($cliente->vendedor?->comissao_pct);
        $comissao = Comissao::cents($lucro, $pct);

        $fatura = DB::transaction(function () use ($cliente, $competencia, $plano, $realizado, $faturado, $total, $impostoBps, $imposto, $custo, $lucro, $comissao, $pct, $bruto, $franquia, $excedente, $itens) {
            $fatura = Fatura::create([
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
                'comissao_pct' => $pct,
                'comissao_cents' => $comissao,
                'situacao_pagamento' => Fatura::PAGAMENTO_PENDENTE,
                'consumo_bruto_cents' => $bruto,
                'franquia_cents' => $franquia,
                'consumo_excedente_cents' => $excedente,
                'fechada_em' => now(),
            ]);

            $fatura->itens()->createMany($itens);
            Auditar::registrar('fatura.fechada', $fatura, [
                'competencia' => $competencia,
                'total_cents' => $total,
                'franquia_cents' => $franquia,
            ]);

            return $fatura;
        });

        try {
            app(CriarCobrancaAsaas::class)($fatura);
        } catch (\Throwable $e) {
            // A fatura interna permanece válida; a cobrança externa pode ser
            // reprocessada sem fechar a competência de novo.
            Log::channel('auditoria')->error('cobranca.asaas_falhou', ['fatura_id' => $fatura->id]);
        }

        return ['erro' => null, 'fatura' => $fatura];
    }
}
