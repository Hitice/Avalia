<?php

namespace App\Support;

/**
 * Como uma venda se reparte entre quem vendeu e quem esta acima dele.
 *
 * Calculo puro: recebe a linha de parceiros, do vendedor ao topo, e devolve
 * quanto cada um leva. Nao consulta banco nem provedor, entao cada regra se
 * testa isolada e uma divisao de tres niveis se explica sem reproduzir o
 * estado do sistema no dia.
 *
 * Duas invariantes, e as duas existem porque o provedor recusa a cobranca
 * inteira quando sao violadas:
 *
 *   1. A soma dos percentuais nunca passa de 100%. Se a linha inteira somar
 *      mais que isso, a venda nao sai, e e melhor descobrir aqui do que na
 *      recusa do provedor com o cliente esperando na tela.
 *   2. A casa fica com o que sobra, e a sobra nunca e negativa.
 *
 * O percentual de cada um vem do cadastro dele, e nao de uma tabela por nivel:
 * comissao e negociacao individual, e um parceiro que renegocia nao pode mexer
 * no que os outros do mesmo nivel recebem.
 */
final class Cascata
{
    /**
     * A divisao de uma venda, do vendedor ao topo.
     *
     * @param  list<array{wallet: string, bps: int, nome: string}>  $linha  Do vendedor para cima.
     * @return array{beneficiarios: list<array{wallet: string, bps: int, percentual: float, nome: string}>, casa_bps: int}
     */
    public static function repartir(array $linha): array
    {
        $beneficiarios = [];
        $somado = 0;

        foreach ($linha as $parceiro) {
            $bps = max(0, (int) ($parceiro['bps'] ?? 0));

            // Parceiro sem carteira nao entra no split: o provedor recusaria a
            // cobranca inteira por causa dele, e a venda de quem esta embaixo
            // nao pode parar porque alguem acima nao terminou o cadastro. O
            // percentual dele fica com a casa, e o painel mostra a pendencia.
            if ($bps === 0 || blank($parceiro['wallet'] ?? null)) {
                continue;
            }

            $somado += $bps;

            $beneficiarios[] = [
                'id' => $parceiro['id'] ?? null,
                'wallet' => $parceiro['wallet'],
                'bps' => $bps,
                // O provedor recebe percentual com duas casas.
                'percentual' => round($bps / 100, 2),
                'nome' => $parceiro['nome'] ?? '',
            ];
        }

        if ($somado > 10000) {
            throw new \RuntimeException(
                'A linha de comissões soma '.number_format($somado / 100, 2, ',', '.').'%, acima de 100%. '
                .'Ajuste os percentuais antes de vender.',
            );
        }

        return ['beneficiarios' => $beneficiarios, 'casa_bps' => 10000 - $somado];
    }

    /**
     * O array `split` como o provedor espera.
     *
     * @param  list<array{wallet: string, bps: int, nome: string}>  $linha
     * @return list<array{walletId: string, percentualValue: float}>
     */
    public static function split(array $linha): array
    {
        return array_map(
            fn (array $b) => ['walletId' => $b['wallet'], 'percentualValue' => $b['percentual']],
            self::repartir($linha)['beneficiarios'],
        );
    }

    /** O que cada um leva, em centavos, de um pagamento liquido. */
    public static function emCentavos(array $linha, int $liquidoCents): array
    {
        $divisao = self::repartir($linha);
        $valores = [];
        $distribuido = 0;

        foreach ($divisao['beneficiarios'] as $b) {
            $valor = intdiv($liquidoCents * $b['bps'], 10000);
            $distribuido += $valor;
            $valores[] = $b + ['valor_cents' => $valor];
        }

        // A casa absorve a sobra da divisao inteira, do mesmo jeito que absorve
        // a diferenca no rateio de uma parcela: um centavo a mais para a casa
        // nunca gerou reclamacao, um centavo a menos no repasse sim.
        return ['beneficiarios' => $valores, 'casa_cents' => $liquidoCents - $distribuido];
    }
}
