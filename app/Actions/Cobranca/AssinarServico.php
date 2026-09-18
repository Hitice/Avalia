<?php

namespace App\Actions\Cobranca;

use App\Models\Parcela360;
use App\Models\Pedido360;
use App\Services\AsaasClient;
use App\Support\Cascata;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Poe de pe a cobranca mensal de um servico contratado.
 *
 * O provedor gera uma cobranca por mes e aplica o mesmo split em cada uma,
 * entao o vendedor e quem esta acima dele recebem na recorrencia inteira sem
 * ninguem apurar comissao por fora. E a diferenca entre cobrar doze vezes e
 * cobrar enquanto o servico durar: aqui nao existe total contratado, existe
 * uma mensalidade que para quando o servico para.
 *
 * A primeira cobranca vence no melhor dia escolhido pelo cliente, respeitando
 * o minimo do provedor: boleto emitido para daqui a dois dias e boleto que
 * vence antes de o cliente conseguir pagar.
 */
class AssinarServico
{
    /** O provedor recusa vencimento antes disto, e o mercado tambem nao gosta. */
    private const DIAS_MINIMOS = 3;

    public function __construct(private readonly AsaasClient $asaas) {}

    public function __invoke(Pedido360 $pedido): Pedido360
    {
        if (filled($pedido->asaas_subscription_id)) {
            return $pedido;
        }

        $oferta = $pedido->oferta;
        $produtor = $pedido->produtor;

        if (! $oferta->ehMensal()) {
            throw new \RuntimeException('Esta oferta é parcelada: use a emissão do carnê.');
        }

        // A conta da rede vem antes de qualquer chamada: linha errada faria a
        // recusa chegar depois de ja termos criado cliente e assinatura.
        $split = Cascata::split($produtor->linhaDeComissao());

        $clienteId = $pedido->asaas_customer_id ?: $this->cliente($pedido);
        $primeiro = $this->primeiroVencimento($pedido->melhor_dia);

        $resposta = $this->asaas->criarAssinatura([
            'customer' => $clienteId,
            'billingType' => 'BOLETO',
            'value' => round($oferta->valor_cents / 100, 2),
            'nextDueDate' => $primeiro->format('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => $oferta->titulo,
            'externalReference' => 'a360-'.$pedido->id,
            // Quando o servico tem prazo, a recorrencia para sozinha. Sem
            // prazo, ela roda ate alguem cancelar, que e o caso de processo
            // sem data para acabar.
            'endDate' => $oferta->meses
                ? $primeiro->copy()->addMonths($oferta->meses - 1)->format('Y-m-d')
                : null,
            'split' => $split,
        ]);

        if (blank($resposta['id'] ?? null)) {
            throw new \RuntimeException('O provedor não devolveu a assinatura criada.');
        }

        return DB::transaction(function () use ($pedido, $resposta, $primeiro, $oferta) {
            $pedido->update([
                'asaas_subscription_id' => $resposta['id'],
                'situacao' => 'efetivado',
                'efetivado_em' => now(),
            ]);

            // A primeira mensalidade ja existe como linha aqui, para o painel
            // mostrar o que vem antes de o provedor avisar. As seguintes
            // nascem quando as cobrancas dele chegarem pelo webhook.
            Parcela360::firstOrCreate(
                ['pedido_360_id' => $pedido->id, 'numero' => 1],
                [
                    'competencia' => $primeiro->format('Y-m'),
                    'valor_cents' => $oferta->valor_cents,
                    'vencimento' => $primeiro,
                ],
            );

            return $pedido->fresh();
        });
    }

    /**
     * O primeiro vencimento no dia escolhido, nunca antes do minimo.
     *
     * Dia ja passado neste mes cai no mes seguinte, e nao amanha: cliente que
     * escolheu dia 10 e contratou no dia 20 espera pagar em 10 do mes que vem.
     */
    private function primeiroVencimento(?int $melhorDia): \Illuminate\Support\Carbon
    {
        $limite = today()->addDays(self::DIAS_MINIMOS);
        $dia = $melhorDia ?: (int) today()->day;

        $candidato = today()->day(min($dia, today()->daysInMonth));

        if ($candidato->lt($limite)) {
            $proximo = today()->addMonthNoOverflow()->startOfMonth();
            $candidato = $proximo->day(min($dia, $proximo->daysInMonth));
        }

        return $candidato;
    }

    private function cliente(Pedido360 $pedido): string
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
