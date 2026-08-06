<?php

namespace App\Support;

/**
 * Quanto a Avalia ganha com um cliente num mes.
 *
 * A margem por servico responde "este preco da lucro?". Esta conta responde a
 * outra pergunta, que e a que se faz na hora de fechar contrato: "este cliente,
 * neste plano, consumindo isto, da quanto?".
 *
 * A conta e uma cascata, e cada degrau segue o PDD (secoes 5 e 9):
 *
 *   fatura   = mensalidade + o MAIOR entre consumo realizado e consumo minimo
 *   imposto  = aliquota sobre a fatura inteira, que e o que a nota diz
 *   custo    = so sobre o consumo realizado: consulta que nao aconteceu nao
 *              tem custo de fornecedor, mesmo que o cliente pague o minimo
 *   lucro    = fatura - imposto - custo
 *   comissao = 10% do lucro
 *
 * Dai vem o efeito que interessa ao comercial: cliente que paga o minimo sem
 * usar e o mais lucrativo, porque o piso da fatura entra inteiro no lucro sem
 * gerar custo de fornecedor.
 */
final class Simulacao
{
    /**
     * @param  int  $custoSobreVendaBps  quanto do preco de venda vai para o
     *                                   fornecedor, em pontos-base
     * @return array{
     *     consumo_faturado_cents: int, fatura_cents: int, imposto_cents: int,
     *     custo_cents: int, lucro_antes_comissao_cents: int, comissao_cents: int,
     *     lucro_cents: int, margem_pct: float|null, pagou_sem_usar_cents: int
     * }
     */
    public static function mensal(
        int $consumoCents,
        int $consumoMinimoCents,
        int $mensalidadeCents,
        int $custoSobreVendaBps,
        int $impostoBps,
        // Aliquota do vendedor simulado. Nula usa o padrao, que e o cenario
        // certo quando ainda nao se sabe quem vai atender a conta.
        ?int $comissaoPct = null,
    ): array {
        $consumo = max(0, $consumoCents);
        $minimo = max(0, $consumoMinimoCents);

        // O minimo e piso de cobranca, nao de consumo: o cliente paga o piso
        // mas so consome o que consumiu.
        $faturado = max($consumo, $minimo);
        $fatura = max(0, $mensalidadeCents) + $faturado;

        $imposto = Margem::impostoCents($fatura, $impostoBps);

        // Custo le consumo realizado, nao o faturado: consulta que nao
        // aconteceu nao tem custo, mesmo que o cliente pague o minimo.
        $custo = (int) round($consumo * max(0, min($custoSobreVendaBps, 10_000)) / 10_000);

        $lucroAntes = $fatura - $imposto - $custo;
        $comissao = Comissao::cents($lucroAntes, $comissaoPct);
        $lucro = $lucroAntes - $comissao;

        return [
            'consumo_faturado_cents' => $faturado,
            'fatura_cents' => $fatura,
            'imposto_cents' => $imposto,
            'custo_cents' => $custo,
            'lucro_antes_comissao_cents' => $lucroAntes,
            'comissao_cents' => $comissao,
            'lucro_cents' => $lucro,
            'margem_pct' => $fatura === 0 ? null : round($lucro * 100 / $fatura, 1),
            'pagou_sem_usar_cents' => max(0, $minimo - $consumo),
        ];
    }

    /**
     * O mesmo mes, mais a parcela da adesao que cai nele.
     *
     * A adesao e rateada meio a meio com o vendedor e so vira receita conforme
     * as parcelas sao liquidadas, entao entra parcela a parcela e nao de uma vez
     * (PDD.md, secao 9).
     *
     * @param  array<string, mixed>  $mes
     * @return array{parcela_cents: int, avalia_cents: int, vendedor_cents: int}
     */
    public static function adesaoDoMes(int $adesaoCents, int $parcelas): array
    {
        $parcelas = max(1, $parcelas);
        $parcela = (int) round(max(0, $adesaoCents) / $parcelas);

        return [
            'parcela_cents' => $parcela,
            'avalia_cents' => $parcela - Comissao::parteAdesaoCents($parcela),
            'vendedor_cents' => Comissao::parteAdesaoCents($parcela),
        ];
    }
}
