<?php

namespace App\Http\Controllers;

use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\CobrancaAsaas;
use App\Models\EventoAsaas;
use Illuminate\Http\Request;

class WebhookAsaasController extends Controller
{
    public function __invoke(Request $request, RegistrarLiquidacao $liquidar)
    {
        $token = (string) config('services.asaas.webhook_token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->header('asaas-access-token')), 403);

        $payload = $request->all();
        $pagamento = (array) ($payload['payment'] ?? []);
        $externo = (string) ($payload['id'] ?? hash('sha256', $request->getContent()));
        $evento = EventoAsaas::firstOrCreate(
            ['evento_externo' => $externo],
            ['tipo' => (string) ($payload['event'] ?? 'desconhecido'), 'payload' => $payload, 'recebido_em' => now()],
        );

        if ($evento->wasRecentlyCreated) {
            $cobranca = CobrancaAsaas::where('asaas_charge_id', $pagamento['id'] ?? null)->first();
            $evento->update(['cobranca_asaas_id' => $cobranca?->id]);

            if ($cobranca) {
                $cobranca->update(['situacao' => $pagamento['status'] ?? $cobranca->situacao, 'resposta' => $pagamento]);

                if (in_array($payload['event'] ?? '', ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true) && $cobranca->fatura) {
                    $liquidar($cobranca->fatura, isset($pagamento['paymentDate']) ? new \DateTimeImmutable($pagamento['paymentDate']) : null);
                }
            }

            $evento->update(['processado_em' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
