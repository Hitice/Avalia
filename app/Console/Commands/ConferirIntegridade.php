<?php

namespace App\Console\Commands;

use App\Models\CobrancaAsaas;
use App\Models\Consulta;
use App\Models\EventoAsaas;
use App\Models\Fatura;
use App\Support\Auditar;
use App\Support\Dinheiro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Confere se os numeros ainda batem entre si.
 *
 * Cada conferencia aqui existe porque a divergencia correspondente e silenciosa:
 * nada quebra, nenhuma tela mostra erro, e o problema aparece semanas depois no
 * extrato de alguem. Roda todo dia e grita quando algo nao fecha.
 *
 * Devolve codigo de saida diferente de zero quando ha divergencia, para o
 * agendador ou o monitoramento tratarem como falha.
 */
class ConferirIntegridade extends Command
{
    protected $signature = 'avalia:conferir';

    protected $description = 'Confere fechamento, cobrancas, webhooks e trilha de auditoria';

    public function handle(): int
    {
        $achados = array_merge(
            $this->fechamentoBateComOConsumo(),
            $this->faturasSemCobranca(),
            $this->cobrancasSemIdentificadorExterno(),
            $this->webhooksSemCobranca(),
            $this->trilhaIntegra(),
        );

        foreach ($achados as $achado) {
            $this->components->error($achado);
            Log::channel('auditoria')->error('integridade.divergencia', ['achado' => $achado]);
        }

        if ($achados === []) {
            $this->components->info('Nada divergente.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * A fatura precisa valer mensalidade mais consumo, e a soma das partes
     * precisa valer o total. Se uma das duas nao fechar, o erro esta entre a
     * consulta e a cobranca, e e dinheiro.
     *
     * @return list<string>
     */
    private function fechamentoBateComOConsumo(): array
    {
        $achados = [];

        foreach (Fatura::with('cliente')->get() as $fatura) {
            $partes = $fatura->imposto_cents + $fatura->custo_cents
                + $fatura->comissao_cents + $fatura->lucro_cents;

            if ($partes !== $fatura->total_cents) {
                $achados[] = sprintf(
                    'Fatura %d (%s, %s): partes somam %s e o total e %s.',
                    $fatura->id,
                    $fatura->cliente?->razao_social ?? 'empresa removida',
                    $fatura->competencia,
                    Dinheiro::brl($partes),
                    Dinheiro::brl($fatura->total_cents),
                );
            }

            $esperado = $fatura->mensalidade_cents + $fatura->consumo_faturado_cents;

            if ($esperado !== $fatura->total_cents) {
                $achados[] = sprintf(
                    'Fatura %d (%s): mensalidade mais consumo dao %s e o total e %s.',
                    $fatura->id,
                    $fatura->competencia,
                    Dinheiro::brl($esperado),
                    Dinheiro::brl($fatura->total_cents),
                );
            }

            $consumo = (int) Consulta::query()
                ->where('cliente_id', $fatura->cliente_id)
                ->where('competencia', $fatura->competencia)
                ->where('situacao', Consulta::SUCESSO)
                ->sum('preco_cents');

            if ($consumo !== $fatura->consumo_bruto_cents) {
                $achados[] = sprintf(
                    'Fatura %d (%s): consultas somam %s e a fatura registrou %s de consumo bruto.',
                    $fatura->id,
                    $fatura->competencia,
                    Dinheiro::brl($consumo),
                    Dinheiro::brl($fatura->consumo_bruto_cents),
                );
            }
        }

        return $achados;
    }

    /** Fatura sem cobranca e dinheiro que ninguem vai pedir. @return list<string> */
    private function faturasSemCobranca(): array
    {
        return Fatura::query()
            ->whereIn('situacao_pagamento', [Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO])
            ->whereDoesntHave('cobrancaAsaas')
            ->get()
            ->map(fn (Fatura $f) => "Fatura {$f->id} ({$f->competencia}) esta em aberto e nao tem cobranca.")
            ->all();
    }

    /**
     * Cobranca sem identificador externo nunca chegou ao provedor: o cliente
     * nao recebeu boleto nenhum e ninguem foi avisado.
     *
     * @return list<string>
     */
    private function cobrancasSemIdentificadorExterno(): array
    {
        // Sem provedor configurado, cobranca sem identificador e o esperado, e
        // nao divergencia: acusar isso todo dia ensinaria a ignorar o relatorio
        // justamente antes de ele passar a dizer algo.
        if (! app(\App\Services\AsaasClient::class)->configurado()) {
            return [];
        }

        return CobrancaAsaas::query()
            ->whereNull('asaas_charge_id')
            ->get()
            ->map(fn (CobrancaAsaas $c) => "Cobranca {$c->id} nao tem identificador no provedor.")
            ->all();
    }

    /**
     * Evento recebido sem cobranca correspondente pode ser cobranca criada fora
     * do sistema, ou identificador que se perdeu. Nos dois casos, alguem
     * pagou algo que nao sabemos reconhecer.
     *
     * @return list<string>
     */
    private function webhooksSemCobranca(): array
    {
        return EventoAsaas::query()
            ->whereNull('cobranca_asaas_id')
            ->get()
            ->map(fn (EventoAsaas $e) => "Evento {$e->evento_externo} ({$e->tipo}) chegou sem cobranca correspondente.")
            ->all();
    }

    /** @return list<string> */
    private function trilhaIntegra(): array
    {
        $quebrados = Auditar::conferir();

        return $quebrados === []
            ? []
            : ['Trilha de auditoria adulterada nos registros: '.implode(', ', $quebrados).'.'];
    }
}
