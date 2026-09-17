<?php

namespace App\Actions\Cobranca;

use App\Models\Parcela360;
use App\Models\Pedido360;
use Illuminate\Support\Facades\DB;

/**
 * Cria as parcelas do carne depois que a venda se efetivou.
 *
 * So roda com contrato assinado E entrada paga. As duas condicoes moram em
 * `Pedido360::podeParcelar()`, e nao aqui, porque a mesma pergunta e feita na
 * tela do produtor e no webhook.
 *
 * Idempotente: pedido que ja tem carne nao ganha outro. O webhook da entrada
 * pode chegar duas vezes, e a segunda nao pode dobrar a divida do cliente.
 *
 * A sobra da divisao vai na PRIMEIRA parcela, e nao na ultima: e o que o
 * mercado faz, e cliente confere o valor da primeira, nao o da decima segunda.
 * As parcelas caem no melhor dia escolhido, contadas a partir do mes seguinte
 * ao da entrada.
 */
class GerarCarne
{
    public function __invoke(Pedido360 $pedido): int
    {
        if (! $pedido->podeParcelar()) {
            return 0;
        }

        if ($pedido->parcelas()->where('numero', '>', 0)->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($pedido) {
            $restante = $pedido->valor_total_cents - $pedido->entrada_cents;
            $valor = intdiv($restante, max(1, $pedido->parcelas));
            $sobra = $restante - ($valor * $pedido->parcelas);

            $dia = $pedido->melhor_dia ?: (int) today()->day;
            $base = today()->startOfMonth();

            for ($numero = 1; $numero <= $pedido->parcelas; $numero++) {
                $mes = $base->copy()->addMonths($numero);

                // Dia 31 em mes de 30 cai no ultimo dia, e nao vira dia 1 do
                // mes seguinte: vencimento que pula de mes bagunca o carne.
                $vencimento = $mes->copy()->day(min($dia, $mes->daysInMonth));

                Parcela360::create([
                    'pedido_360_id' => $pedido->id,
                    'numero' => $numero,
                    'valor_cents' => $numero === 1 ? $valor + $sobra : $valor,
                    'vencimento' => $vencimento,
                ]);
            }

            $pedido->update(['situacao' => 'efetivado', 'efetivado_em' => now()]);

            return $pedido->parcelas;
        });
    }
}
