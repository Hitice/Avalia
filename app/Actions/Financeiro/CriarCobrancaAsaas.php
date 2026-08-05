<?php

namespace App\Actions\Financeiro;

use App\Models\CobrancaAsaas;
use App\Models\Fatura;
use App\Services\AsaasClient;
use App\Support\Auditar;

class CriarCobrancaAsaas
{
    public function __construct(private readonly AsaasClient $asaas) {}

    public function __invoke(Fatura $fatura): CobrancaAsaas
    {
        $cobranca = CobrancaAsaas::firstOrCreate(
            ['fatura_id' => $fatura->id],
            [
                'cliente_id' => $fatura->cliente_id,
                'valor_cents' => $fatura->total_cents,
                'vencimento' => $fatura->vencimento(),
            ],
        );

        if ($cobranca->asaas_charge_id || ! $this->asaas->configurado()) {
            return $cobranca;
        }

        $cliente = $fatura->cliente;
        if (! $cliente->asaas_customer_id) {
            $externo = $this->asaas->criarCliente([
                'name' => $cliente->razao_social,
                'email' => $cliente->email,
                'cpfCnpj' => $cliente->cnpj,
            ]);
            $cliente->update(['asaas_customer_id' => $externo['id']]);
        }

        $resposta = $this->asaas->criarCobranca([
            'customer' => $cliente->fresh()->asaas_customer_id,
            'billingType' => $cobranca->tipo_cobranca,
            'value' => $fatura->total_cents / 100,
            'dueDate' => $fatura->vencimento()->format('Y-m-d'),
            'externalReference' => 'fatura:'.$fatura->id,
            'description' => 'Avalia - competência '.$fatura->competencia,
        ]);

        $cobranca->update([
            'asaas_charge_id' => $resposta['id'],
            'situacao' => $resposta['status'] ?? 'PENDING',
            'invoice_url' => $resposta['invoiceUrl'] ?? null,
            'bank_slip_url' => $resposta['bankSlipUrl'] ?? null,
            'pix_copia_cola' => $resposta['pixTransaction'] ?? null,
            'resposta' => $resposta,
        ]);

        Auditar::registrar('cobranca.criada', $cobranca, ['fatura_id' => $fatura->id]);

        return $cobranca->fresh();
    }
}
