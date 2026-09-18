<?php

namespace App\Http\Controllers;

use App\Actions\Cobranca\RegistrarMensalidade;
use App\Actions\Cobranca\RegistrarPagamentoDaParcela;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\CobrancaAsaas;
use App\Models\EventoAsaas;
use App\Models\Parcela360;
use App\Services\AsaasClient;
use Illuminate\Http\Request;

class WebhookAsaasController extends Controller
{
    public function __invoke(
        Request $request,
        RegistrarLiquidacao $liquidar,
        RegistrarPagamentoDaParcela $baixarParcela,
        RegistrarMensalidade $registrarMensalidade,
        AsaasClient $asaas,
    ) {
        // O token vem da tela de Conexoes (com o .env de reserva): o mesmo
        // cofre que guarda a chave da API guarda o segredo do webhook.
        $token = $asaas->tokenDoWebhook();
        abort_unless($token !== '' && hash_equals($token, (string) $request->header('asaas-access-token')), 403);

        $payload = $request->all();
        $pagamento = (array) ($payload['payment'] ?? []);
        $externo = (string) ($payload['id'] ?? hash('sha256', $request->getContent()));
        $evento = EventoAsaas::firstOrCreate(
            ['evento_externo' => $externo],
            ['tipo' => (string) ($payload['event'] ?? 'desconhecido'), 'payload' => $payload, 'recebido_em' => now()],
        );

        // Reentrega so e ignorada se a primeira ja tiver terminado. Antes bastava
        // o evento existir, e uma entrega interrompida no meio ficava assim para
        // sempre: o provedor reentregava, encontrava o registro e desistia, e o
        // pagamento ficava confirmado la e em aberto aqui, sem ninguem saber.
        if ($evento->wasRecentlyCreated || $evento->processado_em === null) {
            // `where(coluna, null)` vira `whereNull` no Eloquent, e
            // `asaas_charge_id` e nulo em toda cobranca ainda nao emitida.
            // Sem esta guarda, evento sem pagamento (ACCOUNT_STATUS_UPDATED,
            // TRANSFER_*) casava com uma cobranca qualquer e sobrescrevia a
            // situacao dela.
            $idDaCobranca = $pagamento['id'] ?? null;

            $cobranca = filled($idDaCobranca)
                ? CobrancaAsaas::whereNotNull('asaas_charge_id')->where('asaas_charge_id', $idDaCobranca)->first()
                : null;

            // Cobranca de assinatura chega sem linha nossa: o provedor a criou
            // sozinho quando o mes virou. Ela e registrada agora, senao o
            // pagamento seria ignorado e o painel diria que nada foi pago
            // enquanto o dinheiro cai na conta do parceiro.
            if (! $cobranca && filled($pagamento['subscription'] ?? null)) {
                $registrarMensalidade($pagamento);

                $cobranca = CobrancaAsaas::whereNotNull('asaas_charge_id')
                    ->where('asaas_charge_id', $idDaCobranca)
                    ->first();
            }
            $evento->update(['cobranca_asaas_id' => $cobranca?->id]);

            if ($cobranca) {
                $cobranca->update(['situacao' => $pagamento['status'] ?? $cobranca->situacao, 'resposta' => $pagamento]);

                $tipo = (string) ($payload['event'] ?? '');
                $pago = in_array($tipo, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true);

                if ($pago && $cobranca->fatura) {
                    $liquidar($cobranca->fatura, isset($pagamento['paymentDate']) ? new \DateTimeImmutable($pagamento['paymentDate']) : null);
                }

                // A mesma cobranca serve fatura da Avalia One e parcela do
                // Avalia 360; quem decide o que fazer e o vinculo que ela tem,
                // e nao o tipo do evento.
                $parcela = Parcela360::where('cobranca_asaas_id', $cobranca->id)->first();

                if ($parcela && $pago) {
                    $baixarParcela($parcela, $pagamento, $evento);
                }

                // Vencido nao e calendario nosso: e o provedor dizendo que o
                // prazo passou sem pagamento. A parcela e o pedido mudam
                // juntos, senao a regua de cobranca olha para um e o painel
                // do produtor para o outro.
                if ($parcela && $tipo === 'PAYMENT_OVERDUE' && $parcela->situacao !== 'paga') {
                    $parcela->update(['situacao' => 'vencida']);
                    $parcela->pedido->update(['situacao_financeira' => 'inadimplente']);
                }
            }

            $evento->update(['processado_em' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
