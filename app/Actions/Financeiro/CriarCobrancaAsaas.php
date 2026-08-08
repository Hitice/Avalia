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

        if (! $this->asaas->configurado()) {
            return $cobranca;
        }

        // Cobranca ja emitida: nao se cria outra, so se renovam os enderecos.
        // Link de pagamento e boleto expiram, e cobrar duas vezes pela mesma
        // competencia e o pior erro possivel neste caminho.
        if ($cobranca->asaas_charge_id) {
            return $this->renovar($cobranca);
        }

        $cliente = $fatura->cliente;
        if (! $cliente->asaas_customer_id) {
            $externo = $this->asaas->criarCliente([
                'name' => $cliente->razao_social,
                'email' => $cliente->email,
                'cpfCnpj' => $cliente->cnpj,
            ]);

            // Atribuicao direta, e nao update(): o id do provedor nao e campo
            // de formulario e nao esta em fillable, entao update() o descartava
            // em silencio. O efeito era uma cobranca sem cliente, recusada com
            // 400, e um cliente orfao criado no provedor a cada tentativa.
            $cliente->asaas_customer_id = $externo['id'] ?? null;
            $cliente->save();
        }

        // Sem cliente no provedor nao ha cobranca possivel, e insistir so
        // multiplica cadastro orfao la dentro.
        if (! $cliente->asaas_customer_id) {
            throw new \RuntimeException('O provedor não devolveu o cadastro do cliente.');
        }

        $resposta = $this->asaas->criarCobranca([
            'customer' => $cliente->asaas_customer_id,
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

    /** Reconsulta a cobranca no provedor e atualiza os enderecos guardados. */
    private function renovar(CobrancaAsaas $cobranca): CobrancaAsaas
    {
        $atual = $this->asaas->cobranca($cobranca->asaas_charge_id);

        if ($atual === []) {
            return $cobranca;
        }

        $cobranca->update(array_filter([
            'situacao' => $atual['status'] ?? null,
            'invoice_url' => $atual['invoiceUrl'] ?? null,
            'bank_slip_url' => $atual['bankSlipUrl'] ?? null,
        ]));

        return $cobranca->fresh();
    }
}
