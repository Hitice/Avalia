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

    /** Quita uma divida ja reconhecida (comissao a pagar). Sai do sistema, nunca do formulario. */
    case Pagamento = 'pagamento';

    /** A parte do socio que sai na sexta. Despesa de pessoal, nao retirada. */
    case Prolabore = 'prolabore';

    /** Despesa reconhecida antes de pagar: divida com fornecedor. Sai da tela de contas a pagar. */
    case Provisao = 'provisao';

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
            self::Pagamento => 'Pagamento de dívida',
            self::Prolabore => 'Pró-labore',
            self::Provisao => 'Despesa a pagar',
        };
    }

    /**
     * O que esta natureza faz com o dinheiro, em uma frase.
     *
     * Vira a confirmacao depois de gravar. "Lancamento registrado" nao diz se a
     * pessoa escolheu a natureza certa, e a primeira duvida real que chegou foi
     * exatamente essa: despesa paga pelo socio nao tirou nada do caixa, e quem
     * lancou achou que era defeito. Estava certo; a tela e que nao contava.
     *
     * `{socio}` e `{valor}` sao trocados por quem chama.
     */
    public function efeito(): string
    {
        return match ($this) {
            self::Aporte => 'O caixa subiu {valor} e o patrimônio de {socio} também. Não é receita.',
            self::Emprestimo => 'O caixa subiu {valor} e a empresa passou a dever esse valor a {socio}.',
            self::Despesa => 'O caixa caiu {valor}.',
            self::DespesaDoSocio => 'O caixa não se move: quem pagou foi {socio}, e a empresa passou a dever {valor} a ele.',
            self::Reembolso => 'O caixa caiu {valor} e a dívida com {socio} diminuiu no mesmo tanto. Não gera despesa nova.',
            self::Receita => 'O caixa subiu {valor}.',
            self::Transferencia => 'Saiu de uma conta e entrou na outra. O resultado não muda.',
            self::Retirada => 'O caixa caiu {valor} e o que a empresa devia a {socio} diminuiu.',
            self::Distribuicao => 'O caixa caiu {valor} e o patrimônio de {socio} diminuiu.',
            self::Pagamento => 'O caixa caiu {valor} e a dívida diminuiu no mesmo tanto. Não gera despesa nova.',
            self::Prolabore => 'O caixa caiu {valor}: pró-labore de {socio}.',
            self::Provisao => 'A despesa entrou no mês e a empresa passou a dever {valor} ao fornecedor. O caixa só cai no pagamento.',
        };
    }

    /** A natureza precisa saber de qual socio se trata? */
    public function exigeSocio(): bool
    {
        return in_array($this, [
            self::Aporte, self::Emprestimo, self::DespesaDoSocio,
            self::Reembolso, self::Retirada, self::Distribuicao, self::Prolabore,
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
        return in_array($this, [self::Despesa, self::DespesaDoSocio, self::Receita, self::Prolabore, self::Provisao], true);
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

    /** As que o formulario oferece: Pagamento nasce da tela que sabe a quem se deve. */
    public static function rotulosManuais(): array
    {
        return array_diff_key(self::rotulos(), [self::Pagamento->value => true, self::Provisao->value => true]);
    }

    public static function tentar(?string $valor): ?self
    {
        return $valor === null ? null : self::tryFrom($valor);
    }
}
