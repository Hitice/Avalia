<?php

namespace App\Actions\Cobranca;

use App\Models\CobrancaAsaas;
use App\Models\Parcela360;
use App\Services\AsaasClient;
use App\Support\Cascata;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Emite no provedor a cobranca de uma parcela, ja com o split do produtor.
 *
 * O split vai em PERCENTUAL, e nao em valor: se a cobranca for alterada depois
 * de criada (desconto, juros de atraso), a divisao continua certa sozinha.
 * Valor fixo no split vira erro silencioso justamente nos casos em que o valor
 * muda, que sao os casos em que ninguem esta olhando.
 *
 * Parcela que ja tem cobranca devolve a que existe. Emitir de novo criaria
 * dois boletos para a mesma parcela, e o cliente pagaria o que chegasse
 * primeiro enquanto o outro seguiria vencendo.
 */
class EmitirCobrancaDaParcela
{
    public function __construct(private readonly AsaasClient $asaas) {}

    public function __invoke(Parcela360 $parcela): CobrancaAsaas
    {
        if ($parcela->cobranca_asaas_id !== null) {
            return $parcela->cobranca;
        }

        $pedido = $parcela->pedido;
        $produtor = $pedido->produtor;

        if (! $produtor->podeVender()) {
            throw new \RuntimeException('O produtor não tem carteira no provedor: não há para onde repassar.');
        }

        // O split e montado ANTES de falar com o provedor. A validacao da
        // cascata mora dentro dele, e uma linha que soma mais de 100% faria a
        // cobranca ser recusada la na frente, depois de ja termos criado um
        // cliente que ninguem vai usar.
        $split = Cascata::split($produtor->linhaDeComissao());

        $clienteId = $pedido->asaas_customer_id ?: $this->cliente($pedido);

        $resposta = $this->asaas->criarCobranca([
            'customer' => $clienteId,
            'billingType' => 'BOLETO',
            'value' => round($parcela->valor_cents / 100, 2),
            'dueDate' => $parcela->vencimento->format('Y-m-d'),
            'description' => $parcela->ehEntrada()
                ? 'Entrada: '.$pedido->oferta->titulo
                : sprintf('Parcela %d de %d: %s', $parcela->numero, $pedido->parcelas, $pedido->oferta->titulo),
            // Identificador nosso na ponta do provedor: e por ele que a
            // conciliacao acha a parcela quando o id da cobranca se perde.
            'externalReference' => 'p360-'.$parcela->id,
            // A linha inteira vai num array so: quem vendeu, quem o trouxe e
            // o topo. O provedor divide no momento em que o cliente paga, e o
            // dinheiro nunca passa pela conta da casa.
            'split' => $split,
        ]);

        return DB::transaction(function () use ($parcela, $pedido, $resposta) {
            $cobranca = CobrancaAsaas::create([
                'asaas_charge_id' => $resposta['id'] ?? null,
                'situacao' => $resposta['status'] ?? 'PENDING',
                'tipo_cobranca' => 'parcela_360',
                'valor_cents' => $parcela->valor_cents,
                'vencimento' => $parcela->vencimento,
                'invoice_url' => $resposta['invoiceUrl'] ?? null,
                'bank_slip_url' => $resposta['bankSlipUrl'] ?? null,
                'resposta' => $resposta,
            ]);

            $parcela->update(['cobranca_asaas_id' => $cobranca->id]);

            if (blank($pedido->asaas_customer_id)) {
                $pedido->update(['asaas_customer_id' => $resposta['customer'] ?? null]);
            }

            return $cobranca;
        });
    }

    /** O cliente final no provedor, criado na primeira cobranca do pedido. */
    private function cliente($pedido): string
    {
        $resposta = $this->asaas->criarCliente([
            'name' => $pedido->cliente_nome,
            'email' => $pedido->cliente_email,
            'cpfCnpj' => Documento::normalizarCnpj($pedido->cliente_documento),
            'mobilePhone' => $pedido->cliente_telefone,
            'externalReference' => 'c360-'.$pedido->id,
        ]);

        $id = $resposta['id'] ?? null;

        if (blank($id)) {
            throw new \RuntimeException('O provedor não devolveu o cliente criado.');
        }

        $pedido->update(['asaas_customer_id' => $id]);

        return $id;
    }
}
