<?php

namespace App\Support;

/**
 * Como o dinheiro de uma plaquinha vendida se reparte.
 *
 * Do que o cliente pagou sai primeiro o custo da placa, porque ele e desembolso
 * da casa e nao margem de ninguem. Sobre o que resta incide a comissao de quem
 * vendeu, e o que sobra depois disso se divide entre os socios.
 *
 * A base da comissao e o LIQUIDO, e nao o preco cheio. E a mesma escolha de
 * App\Support\Comissao, pelo mesmo motivo: comissionar faturamento pagaria
 * igual por uma venda que rende e por uma que sangra.
 *
 * Venda feita por socio nao gera comissao. O socio ja recebe pela divisao, e
 * pagar comissao a ele antes da divisao seria pagar a mesma pessoa duas vezes
 * pelo mesmo ato.
 *
 * As partes somam SEMPRE o bruto: custo + comissao + sobra devolvem o que o
 * cliente pagou. A sobra sai por subtracao de proposito, e e o que faz o
 * centavo do arredondamento aparecer em algum lugar em vez de sumir.
 *
 * A comissao arredonda POR VENDA, e a divisao entre socios NAO acontece aqui.
 * A diferenca e deliberada: o vendedor confere a comissao dele placa a placa,
 * entao ela tem de fechar em cada linha; ja a divisao dos socios e do mes
 * inteiro. Dividir placa a placa daria o centavo impar sempre ao mesmo socio,
 * e em cem placas isso vira cinquenta centavos de vies sistematico a favor de
 * quem estiver em primeiro no config. Quem soma o mes chama `dividir` uma vez,
 * no fim.
 */
final class RepartePlaquinha
{
    /**
     * O reparte de uma venda.
     *
     * @return array{bruto: int, custo: int, liquido: int, comissao: int, sobra: int}
     */
    public static function de(
        int $valorCents,
        int $custoCents,
        bool $vendedorEhSocio,
        int $pct,
    ): array {
        $bruto = max(0, $valorCents);

        // O custo nunca passa do que entrou: placa vendida abaixo do custo e
        // prejuizo da casa, e nao comissao negativa para quem vendeu.
        $custo = max(0, min($custoCents, $bruto));
        $liquido = $bruto - $custo;

        $comissao = $vendedorEhSocio ? 0 : self::comissaoCents($liquido, $pct);
        $sobra = $liquido - $comissao;

        return [
            'bruto' => $bruto,
            'custo' => $custo,
            'liquido' => $liquido,
            'comissao' => $comissao,
            'sobra' => $sobra,
        ];
    }

    /**
     * A comissao sobre o liquido, com o percentual limitado ao que faz sentido.
     *
     * Teto emprestado de App\Support\Comissao: percentual invalido digitado no
     * config nao pode virar repasse maior que a venda.
     */
    public static function comissaoCents(int $liquidoCents, int $pct): int
    {
        if ($liquidoCents <= 0) {
            return 0;
        }

        $valido = ($pct < 0 || $pct > Comissao::PCT_MAXIMO) ? Comissao::PCT_MAXIMO : $pct;

        // round, e nao trunca: dois calculos do mesmo mes precisam dar o mesmo
        // centavo, senao o painel e o repasse divergem sem ninguem mexer em nada.
        return (int) round($liquidoCents * $valido / 100);
    }

    /**
     * Divide centavos entre N pessoas sem perder nem inventar centavo.
     *
     * Chamada UMA vez, sobre a sobra ja somada do periodo. Chamar por venda
     * daria o centavo impar sempre ao mesmo socio, e o vies acumularia.
     *
     * A sobra da divisao vai para os primeiros da lista, um centavo cada. Nao e
     * sorteio nem rodizio: precisa ser o mesmo resultado toda vez que a tela
     * recarrega, senao o numero do socio muda sozinho entre dois F5.
     *
     * `$entre` zero nao existe na pratica, mas config vazio existe, e dividir
     * por zero derrubaria o painel em vez de mostrar uma coluna a menos.
     *
     * Espera `$cents` nao negativo, que e o que `de()` sempre entrega: o custo
     * e limitado ao bruto e a comissao ao liquido, entao a sobra nunca vira
     * divida.
     *
     * @return list<int>
     */
    public static function dividir(int $cents, int $entre): array
    {
        if ($entre <= 0) {
            return [];
        }

        $base = intdiv($cents, $entre);
        $resto = $cents - ($base * $entre);

        return array_map(
            fn (int $posicao) => $base + ($posicao < $resto ? 1 : 0),
            range(0, $entre - 1),
        );
    }
}
