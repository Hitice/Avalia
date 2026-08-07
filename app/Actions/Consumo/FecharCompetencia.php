<?php

namespace App\Actions\Consumo;

use App\Actions\Financeiro\CriarCobrancaAsaas;
use App\Mail\FaturaEmitida;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Support\Auditar;
use App\Support\Comissao;
use App\Support\Margem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    public function __construct(private readonly ApurarCompetencia $apurar) {}

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

        // A conta do mes vive em ApurarCompetencia, que o painel do cliente
        // tambem le: a tela e a fatura tem que fechar no centavo.
        $apurado = ($this->apurar)($cliente, $competencia);
        $itens = $apurado['itens'];
        $bruto = $apurado['bruto'];
        $franquia = $apurado['franquia'];
        $excedente = $apurado['excedente'];

        $realizado = $excedente;
        $custo = $apurado['custo'];

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

        // Vale para os dois caminhos, rotina e clique na tela: fatura que so
        // aparece quando alguem abre o portal e fatura que vence sem ninguem
        // saber que existia. Falha de envio nao desfaz o fechamento.
        try {
            if ($cliente->email) {
                Mail::to($cliente->email)->send(new FaturaEmitida($fatura));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return ['erro' => null, 'fatura' => $fatura];
    }
}
