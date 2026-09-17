<?php

namespace App\Support;

use App\Models\Pedido360;
use Carbon\CarbonInterface;

/**
 * Decide se uma proposta de venda parcelada passa, e em quantas vezes.
 *
 * Calculo puro: recebe fatos, devolve decisao. Nao consulta banco nem birô, e
 * e por isso que da para testar cada regra isolada e explicar cada recusa sem
 * reproduzir o estado do sistema no dia.
 *
 * A decisao carrega a VERSAO da regra que decidiu. Sem isso, endurecer o motor
 * em novembro torna impossivel explicar em janeiro por que fulano foi recusado
 * em outubro, e essa pergunta sempre chega.
 *
 * Restricao em birô nao reprova: reduz o teto de parcelas. Recusar quem tem
 * uma negativacao antiga de oitenta reais e recusar venda boa, e quem paga
 * essa conta e o produtor.
 */
final class AnaliseDeCredito
{
    public const APROVADO = 'aprovado';

    public const REPROVADO = 'reprovado';

    /**
     * @param  array{documento: string, nascimento: ?CarbonInterface, financiado_cents: int, parcelas: int, pedidos_em_aberto: int, restricao: bool, na_lista_negra: bool}  $fatos  `financiado_cents` e o que sobra depois da entrada: e ele que vira parcela
     * @return array{decisao: string, motivo: string, teto_parcelas: int, versao: string}
     */
    public static function decidir(array $fatos): array
    {
        $versao = (string) config('cobranca.analise.versao');
        $reprova = fn (string $motivo) => [
            'decisao' => self::REPROVADO,
            'motivo' => $motivo,
            'teto_parcelas' => 0,
            'versao' => $versao,
        ];

        if (! Documento::documentoValido($fatos['documento'] ?? '')) {
            return $reprova('Documento inválido.');
        }

        if ($fatos['na_lista_negra'] ?? false) {
            return $reprova('Documento em lista de bloqueio.');
        }

        // Menor de idade nao assina contrato de credito sozinho, e contrato
        // assinado por menor e contrato que nao se cobra.
        $idadeMinima = (int) config('cobranca.analise.idade_minima');
        $nascimento = $fatos['nascimento'] ?? null;

        if ($nascimento === null) {
            return $reprova('Data de nascimento não informada.');
        }

        if ($nascimento->age < $idadeMinima) {
            return $reprova('Idade mínima de '.$idadeMinima.' anos.');
        }

        // Um carne por vez. Quem ainda deve a compra anterior comprando de
        // novo e a forma mais comum de inadimplencia que ninguem viu chegar.
        if (($fatos['pedidos_em_aberto'] ?? 0) > 0) {
            return $reprova('Já existe uma compra em aberto com este documento.');
        }

        $teto = (int) config('cobranca.parcelamento.maximo');

        if ($fatos['restricao'] ?? false) {
            $teto = min($teto, (int) config('cobranca.analise.teto_com_restricao'));
        }

        // O piso por parcela limita o parcelamento tanto quanto o teto: um
        // ticket pequeno em doze vezes custa mais em cobranca do que rende.
        // O piso incide sobre o que sera parcelado, e nao sobre o total: a
        // entrada ja saiu, e usar o total aqui aprovava parcela abaixo do
        // piso sempre que houvesse entrada. Foi o teste do ticket de R$ 600
        // que mostrou isso.
        $piso = (int) config('cobranca.parcelamento.minimo_parcela_cents');
        $valor = max(0, (int) ($fatos['financiado_cents'] ?? 0));

        if ($piso > 0 && $valor > 0) {
            $teto = min($teto, max(1, intdiv($valor, $piso)));
        }

        if (($fatos['parcelas'] ?? 1) > $teto) {
            return [
                'decisao' => self::APROVADO,
                'motivo' => 'Aprovado com teto de '.$teto.'x.',
                'teto_parcelas' => $teto,
                'versao' => $versao,
            ];
        }

        return [
            'decisao' => self::APROVADO,
            'motivo' => 'Aprovado.',
            'teto_parcelas' => $teto,
            'versao' => $versao,
        ];
    }

    /** A entrada minima que a regra exige para um valor. */
    public static function entradaMinima(int $valorCents): int
    {
        return intdiv($valorCents * (int) config('cobranca.entrada.minimo_bps'), 10000);
    }

    /** Ate quando o cliente pode desistir e receber a entrada de volta. */
    public static function arrependimentoAte(Pedido360 $pedido): ?CarbonInterface
    {
        if ($pedido->contrato_assinado_em === null) {
            return null;
        }

        $dias = $pedido->oferta?->produto?->dias_arrependimento
            ?? (int) config('cobranca.arrependimento_dias');

        return $pedido->contrato_assinado_em->copy()->addDays($dias);
    }
}
