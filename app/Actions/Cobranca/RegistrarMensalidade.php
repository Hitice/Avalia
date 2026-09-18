<?php

namespace App\Actions\Cobranca;

use App\Models\CobrancaAsaas;
use App\Models\Parcela360;
use App\Models\Pedido360;

/**
 * Recebe uma cobranca que o provedor gerou sozinho, mes a mes.
 *
 * No parcelamento o carne inteiro nasce de uma vez e cada boleto ja tem linha
 * aqui. Na mensalidade nao: o provedor cria a cobranca do mes quando chega a
 * hora, e a primeira noticia que temos dela e o webhook. Sem isto, o pagamento
 * de marco chegaria sem parcela correspondente e seria ignorado, com o dinheiro
 * caindo na conta do parceiro e o painel dizendo que nada foi pago.
 *
 * A competencia vem do vencimento, e nao da data do pagamento: a cobranca que
 * vence em 10/03 e a de marco mesmo que o cliente pague em abril.
 */
class RegistrarMensalidade
{
    public function __invoke(array $pagamento): ?Parcela360
    {
        $assinatura = $pagamento['subscription'] ?? null;

        if (blank($assinatura)) {
            return null;
        }

        $pedido = Pedido360::firstWhere('asaas_subscription_id', $assinatura);

        if (! $pedido) {
            return null;
        }

        $cobrancaId = (string) ($pagamento['id'] ?? '');

        if ($cobrancaId === '') {
            return null;
        }

        $vencimento = isset($pagamento['dueDate'])
            ? \Illuminate\Support\Carbon::parse($pagamento['dueDate'])
            : today();

        $valorCents = isset($pagamento['value'])
            ? (int) round(((float) $pagamento['value']) * 100)
            : $pedido->oferta->valor_cents;

        // A parcela do mes pode ja existir: a primeira e criada junto da
        // assinatura, e o webhook do mesmo mes nao pode abrir outra.
        $parcela = Parcela360::firstOrCreate(
            ['pedido_360_id' => $pedido->id, 'competencia' => $vencimento->format('Y-m')],
            [
                'numero' => (int) $pedido->parcelas()->max('numero') + 1,
                'valor_cents' => $valorCents,
                'vencimento' => $vencimento,
            ],
        );

        if ($parcela->cobranca_asaas_id === null) {
            $cobranca = CobrancaAsaas::firstOrCreate(
                ['asaas_charge_id' => $cobrancaId],
                [
                    'situacao' => $pagamento['status'] ?? 'PENDING',
                    'tipo_cobranca' => 'mensalidade_360',
                    'valor_cents' => $valorCents,
                    'vencimento' => $vencimento,
                    'invoice_url' => $pagamento['invoiceUrl'] ?? null,
                    'bank_slip_url' => $pagamento['bankSlipUrl'] ?? null,
                    'resposta' => $pagamento,
                ],
            );

            $parcela->update(['cobranca_asaas_id' => $cobranca->id]);
        }

        return $parcela->fresh();
    }
}
