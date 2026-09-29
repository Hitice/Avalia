<?php

namespace App\Actions\Etiquetas;

use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz a venda da plaquinha, sem tirar a placa de campo.
 *
 * Existe porque apontar e vender sao o mesmo clique hoje, e nem toda placa que
 * entra em campo foi vendida: a da propria Avalia, a de demonstracao, a de
 * teste e a que foi cadastrada por engano. Todas elas nasciam com `vendida_em`
 * preenchido e entravam no faturamento do mes como se alguem tivesse pagado.
 *
 * O que sai e SO o fato comercial. O destino, o codigo e o historico ficam, e a
 * placa continua redirecionando: a da casa aponta para a avaliacao da Avalia no
 * Google e precisa continuar funcionando, ela so nao e receita de ninguem.
 *
 * Sem `vence_em` a placa nao vence mais, pela regra que o model ja tinha: placa
 * sem prazo fica ativa. E o comportamento certo para placa da casa, que nao tem
 * cliente a cobrar nem renovacao a vender, e tambem tira ela do aviso de
 * vencimento, que senao cobraria a gente de renovar a propria placa.
 *
 * Nao e o mesmo que suspender nem que encerrar. Suspender apaga o
 * redirecionamento e mantem a venda; isto mantem o redirecionamento e apaga a
 * venda. Sao perguntas diferentes: "a placa funciona?" e "alguem pagou por
 * ela?".
 *
 * So a administracao cancela, e por um motivo de dinheiro: cancelar venda de
 * mes passado muda a apuracao daquele mes, e com ela a comissao de alguem que
 * talvez ja tenha recebido. A tela avisa disso, e a trilha guarda o que foi
 * desfeito para que o valor antigo continue explicavel depois.
 */
class CancelarVendaEtiqueta
{
    public function __invoke(Etiqueta $etiqueta): Etiqueta
    {
        if ($etiqueta->vendida_em === null) {
            throw new Recusa('Esta plaquinha não tem venda registrada.');
        }

        return DB::transaction(function () use ($etiqueta) {
            // Guardado ANTES de limpar: e o unico registro de quanto a venda
            // valia e de quem era. Sem isso, um repasse antigo conferido contra
            // o painel de hoje nao teria como ser explicado.
            $desfeito = [
                'vendida_em' => $etiqueta->vendida_em->toDateTimeString(),
                'valor_cents' => $etiqueta->valor_cents,
                'custo_cents' => $etiqueta->custo_cents,
                'vendedor_id' => $etiqueta->vendedor_id,
                'vence_em' => $etiqueta->vence_em?->toDateString(),
            ];

            $etiqueta->update([
                'vendida_em' => null,
                'valor_cents' => null,
                'custo_cents' => null,
                'vendedor_id' => null,
                'vence_em' => null,

                // Zerado junto: se a placa voltar a ser vendida um dia, o aviso
                // de vencimento precisa poder sair de novo.
                'avisada_em' => null,
            ]);

            Auditar::registrar('etiquetas.venda.cancelada', $etiqueta, $desfeito);

            return $etiqueta->refresh();
        });
    }
}
