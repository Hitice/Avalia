<?php

namespace App\Actions\Cobranca;

use App\Models\EventoAsaas;
use App\Models\Lancamento360;
use App\Models\Parcela360;
use App\Support\Rateio;
use Illuminate\Support\Facades\DB;

/**
 * Da baixa numa parcela e escreve as quatro linhas do razao.
 *
 * Idempotente pela presenca do lancamento bruto: webhook reentregue nao lanca
 * o mesmo pagamento duas vezes. A checagem e pela PARCELA, e nao pelo evento,
 * porque o provedor reenvia o mesmo pagamento sob ids de evento diferentes
 * quando muda de PAYMENT_CONFIRMED para PAYMENT_RECEIVED.
 *
 * A taxa do provedor vem do proprio pagamento (`netValue`), e nao de tabela
 * nossa: e o unico numero que diz quanto ele realmente descontou, e ele muda
 * por contrato, por meio de pagamento e por promocao.
 */
class RegistrarPagamentoDaParcela
{
    public function __construct(private readonly GerarCarne $gerarCarne) {}

    public function __invoke(Parcela360 $parcela, array $pagamento, ?EventoAsaas $evento = null): void
    {
        $jaLancado = Lancamento360::where('parcela_360_id', $parcela->id)
            ->where('tipo', 'bruto')
            ->exists();

        if ($jaLancado) {
            return;
        }

        $pedido = $parcela->pedido;

        $pagoCents = isset($pagamento['value'])
            ? (int) round(((float) $pagamento['value']) * 100)
            : $parcela->valor_cents;

        // netValue e o que sobrou depois da taxa do provedor. A diferenca para
        // o bruto e a taxa, e e assim que ela entra no razao.
        $liquidoCents = isset($pagamento['netValue'])
            ? (int) round(((float) $pagamento['netValue']) * 100)
            : $pagoCents;

        $partes = Rateio::de($pagoCents, max(0, $pagoCents - $liquidoCents), $pedido->taxa_bps);

        $quando = isset($pagamento['paymentDate'])
            ? new \DateTimeImmutable($pagamento['paymentDate'])
            : now()->toDateTimeImmutable();

        DB::transaction(function () use ($parcela, $pedido, $partes, $quando, $evento) {
            foreach ($partes as $tipo => $valor) {
                if ($valor === 0 && $tipo !== 'bruto') {
                    continue;
                }

                Lancamento360::create([
                    'pedido_360_id' => $pedido->id,
                    'parcela_360_id' => $parcela->id,
                    'tipo' => $tipo,
                    'valor_cents' => $valor,
                    'ocorrido_em' => $quando,
                    'evento_asaas_id' => $evento?->id,
                    'descricao' => $parcela->ehEntrada()
                        ? 'Entrada do pedido '.$pedido->id
                        : 'Parcela '.$parcela->numero.' do pedido '.$pedido->id,
                ]);
            }

            $parcela->update(['situacao' => 'paga', 'paga_em' => $quando]);

            // Entrada paga com contrato ja assinado fecha a venda na hora: o
            // carne nasce aqui, e nao numa rotina noturna. Cliente que pagou a
            // entrada quer ver o parcelamento no mesmo dia, e `GerarCarne`
            // confere as duas condicoes antes de criar qualquer coisa.
            if ($parcela->ehEntrada()) {
                ($this->gerarCarne)($pedido->fresh());
            }

            // Quitado quando nao sobra parcela em aberto. A entrada conta: ela
            // e a parcela zero, e pedido com entrada paga e carne quitado e
            // pedido encerrado.
            if ($pedido->parcelas()->emAberto()->doesntExist()) {
                $pedido->update(['situacao_financeira' => 'quitado']);
            } elseif ($pedido->situacao_financeira === 'inadimplente') {
                $pedido->update(['situacao_financeira' => 'adimplente']);
            }
        });
    }
}
