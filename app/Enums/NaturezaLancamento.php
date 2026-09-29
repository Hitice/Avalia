<?php

namespace App\Enums;

/**
 * O que aconteceu com o dinheiro, em vocabulario de negocio.
 *
 * A tela pergunta a natureza; ela decide as pernas. Quem lanca nao escolhe
 * conta, porque escolher conta e onde o erro mora: aporte lancado como receita
 * infla o resultado, reembolso lancado como despesa cobra a mesma conta duas
 * vezes, e transferencia lancada como saida faz o mes parecer pior do que foi.
 *
 * Cada natureza abaixo diz QUAL CONTA DEBITA e QUAL CREDITA. Debito e credito
 * aqui nao sao "entrada e saida": sao os dois lados que somam zero. Em conta de
 * ativo, debito aumenta; em conta de passivo, patrimonio e receita, credito
 * aumenta. E a convencao contabil de sempre, e ela existe porque fecha sozinha.
 */
enum NaturezaLancamento: string
{
    /** Socio poe dinheiro na empresa. Vira patrimonio, nunca receita. */
    case Aporte = 'aporte';

    /** Socio empresta. Vira divida da empresa com ele, e ele espera de volta. */
    case Emprestimo = 'emprestimo';

    /** Conta da empresa paga pela empresa. */
    case Despesa = 'despesa';

    /** Conta da empresa paga do bolso do socio. Gera despesa E divida com ele. */
    case DespesaDoSocio = 'despesa_do_socio';

    /** A empresa devolve ao socio o que ele adiantou. Quita divida, nao gasta. */
    case Reembolso = 'reembolso';

    /** Dinheiro que entra por venda. */
    case Receita = 'receita';

    /** De uma conta da empresa para outra. Nao muda resultado nem patrimonio. */
    case Transferencia = 'transferencia';

    /** Socio tira dinheiro. Reduz o que a empresa deve a ele ou o patrimonio. */
    case Retirada = 'retirada';

    /** Distribuicao de resultado, que e decisao e nao calculo automatico. */
    case Distribuicao = 'distribuicao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aporte => 'Aporte de capital',
            self::Emprestimo => 'Empréstimo de sócio',
            self::Despesa => 'Despesa',
            self::DespesaDoSocio => 'Despesa paga pelo sócio',
            self::Reembolso => 'Reembolso ao sócio',
            self::Receita => 'Receita',
            self::Transferencia => 'Transferência entre contas',
            self::Retirada => 'Retirada do sócio',
            self::Distribuicao => 'Distribuição de resultado',
        };
    }

    /** A natureza precisa saber de qual socio se trata? */
    public function exigeSocio(): bool
    {
        return in_array($this, [
            self::Aporte, self::Emprestimo, self::DespesaDoSocio,
            self::Reembolso, self::Retirada, self::Distribuicao,
        ], true);
    }

    /**
     * A natureza mexe no resultado do periodo?
     *
     * So receita e despesa mexem. As outras movem dinheiro entre bolsos sem
     * ninguem ficar mais rico ou mais pobre pelo negocio, e e exatamente isso
     * que a tela de resultado precisa respeitar para nao mentir.
     */
    public function afetaResultado(): bool
    {
        return in_array($this, [self::Despesa, self::DespesaDoSocio, self::Receita], true);
    }

    /** @return array<string, string> valor => rotulo */
    public static function rotulos(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $mapa, self $caso) => $mapa + [$caso->value => $caso->rotulo()],
            [],
        );
    }

    public static function tentar(?string $valor): ?self
    {
        return $valor === null ? null : self::tryFrom($valor);
    }
}
