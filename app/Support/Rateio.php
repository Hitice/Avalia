<?php

namespace App\Support;

/**
 * Como um pagamento se reparte entre provedor, plataforma e produtor.
 *
 * A conta e sempre a mesma, e a ordem importa: do que o cliente pagou sai
 * primeiro a taxa do provedor (ele desconta antes de creditar), depois a taxa
 * da plataforma, e o que resta e do produtor. Inverter a ordem faria a
 * plataforma cobrar sobre dinheiro que nunca chegou.
 *
 * As quatro partes somam ZERO de propósito. O bruto entra positivo e as tres
 * saidas saem negativas, entao conferir um pedido e somar a coluna: se nao der
 * zero, faltou lancamento ou sobrou. E a unica invariante que pega erro de
 * arredondamento sem ninguem precisar reconferir extrato.
 *
 * Tudo em centavos inteiros, e a sobra da divisao fica com o produtor: e a
 * parte maior, e um centavo a mais nela nunca gerou reclamacao; um centavo a
 * menos no repasse, sim.
 */
final class Rateio
{
    /**
     * @return array{bruto: int, taxa_provedor: int, taxa_plataforma: int, repasse: int}
     */
    public static function de(int $pagoCents, int $taxaProvedorCents, int $taxaBps): array
    {
        $taxaProvedor = max(0, min($taxaProvedorCents, $pagoCents));

        // A taxa da plataforma incide sobre o que o cliente pagou, e nao sobre
        // o liquido: e o que o contrato diz e o que o produtor ve na proposta.
        $taxaPlataforma = intdiv($pagoCents * $taxaBps, 10000);

        // O produtor nunca recebe negativo. Se as taxas comerem o pagamento
        // inteiro (parcela minuscula, taxa fixa alta), o repasse e zero e a
        // plataforma absorve a diferenca.
        $repasse = max(0, $pagoCents - $taxaProvedor - $taxaPlataforma);
        $taxaPlataforma = $pagoCents - $taxaProvedor - $repasse;

        return [
            'bruto' => $pagoCents,
            'taxa_provedor' => -$taxaProvedor,
            'taxa_plataforma' => -$taxaPlataforma,
            'repasse' => -$repasse,
        ];
    }

    /** O que o produtor recebe de um pagamento, para mostrar na proposta. */
    public static function repasseDe(int $pagoCents, int $taxaProvedorCents, int $taxaBps): int
    {
        return -self::de($pagoCents, $taxaProvedorCents, $taxaBps)['repasse'];
    }

    /**
     * O percentual que o split manda para a carteira do produtor.
     *
     * O provedor recebe percentual, nao valor: assim a divisao continua certa
     * se o valor da cobranca mudar depois de criada. A taxa do provedor fica
     * fora da conta porque ele a desconta antes de aplicar o split.
     */
    public static function percentualDoProdutor(int $taxaBps): float
    {
        return round((10000 - $taxaBps) / 100, 2);
    }
}
