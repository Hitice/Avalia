<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Cliente isolado para que cobrança não dependa de tela ou webhook. */
class AsaasClient
{
    public function configurado(): bool
    {
        return filled(config('services.asaas.api_key'));
    }

    public function criarCliente(array $dados): array
    {
        return $this->http()->post('/customers', $dados)->throw()->json();
    }

    public function criarCobranca(array $dados): array
    {
        return $this->http()->post('/payments', $dados)->throw()->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.asaas.base_url'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['access_token' => config('services.asaas.api_key')])
            ->timeout(15)
            ->retry(2, 500);
    }
}
