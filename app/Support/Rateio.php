<?php

namespace App\Support;

/**
 * Como um pagamento se reparte entre provedor, plataforma e produtor.
 *
 * A conta e sempre a mesma, e a ordem importa: do que o cliente pagou sai
 * primeiro a taxa do provedor (ele desconta antes de creditar), e o que resta
 * e dividido entre plataforma e produtor pelo percentual do split.
 *
 * Essa ordem NAO e escolha de estilo: e a ordem do provedor. O split
 * percentual dele incide sobre o liquido, e o razao precisa chegar ao mesmo
 * numero, senao o extrato do produtor e a nossa contabilidade contam
 * historias diferentes sobre o mesmo pagamento.
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

        // O repasse e calculado como o PROVEDOR calcula, e nao como seria mais
        // intuitivo: o split percentual dele incide sobre o liquido, depois de
        // descontada a taxa dele. Cobrar a taxa da plataforma sobre o bruto
        // aqui fazia o razao divergir do que cai de verdade na carteira do
        // produtor, e a diferenca era de 5% da taxa do provedor por parcela.
        // Num carne de doze, isso vira discussao no extrato, que e o unico
        // numero que o produtor confere todo mes.
        $liquido = $pagoCents - $taxaProvedor;
        $repasse = max(0, intdiv($liquido * (10000 - $taxaBps), 10000));

        // O que sobra e da plataforma. Escrito por subtracao de proposito: e o
        // que garante que as quatro partes fechem em zero mesmo quando a
        // divisao sobra centavo, e quem absorve a sobra e quem cobra a taxa.
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
