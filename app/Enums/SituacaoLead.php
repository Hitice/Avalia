<?php

namespace App\Enums;

/**
 * Em que ponto do funil o lead esta.
 *
 * Substitui a coluna `ativo`, que era o marcador "(INATIVO)" colado no nome pela
 * base de origem. Aquilo dizia algo sobre a situacao cadastral da empresa na
 * Receita, e nada sobre o trabalho: todo lead da base e prospectavel, e a Avalia
 * e nova demais para ter lead "inativo". O que a operacao precisa saber e outra
 * coisa: alguem falou com este lead, e como foi.
 *
 * Enum e nao constante de array, pelo mesmo motivo de Categoria: o tipo passa a
 * ser garantido pelo PHP, e o rotulo anda junto do valor.
 *
 * Nao confundir com a situacao do CLIENTE, que e permissao de consulta e de
 * cobranca. Sao coisas diferentes de proposito, e o lead nao tem nem uma nem
 * outra: ele nao consulta e nao e cobrado.
 */
enum SituacaoLead: string
{
    case Novo = 'novo';
    case Atendendo = 'atendendo';
    case Agendado = 'agendado';
    case Recusado = 'recusado';
    case Bloqueado = 'bloqueado';
    case Convertido = 'convertido';

    public function rotulo(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::Atendendo => 'Em atendimento',
            self::Agendado => 'Agendado',
            self::Recusado => 'Recusado',
            self::Bloqueado => 'Não atender',
            self::Convertido => 'Virou cliente',
        };
    }

    /** A cor acompanha o nome, para o estado ler igual em toda tela. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Novo => 'etiqueta-neutra',
            self::Atendendo => 'etiqueta-alerta',
            self::Agendado => 'etiqueta-alerta',
            self::Recusado => 'etiqueta-erro',
            self::Bloqueado => 'etiqueta-erro',
            self::Convertido => 'etiqueta-sucesso',
        };
    }

    /** Ainda da trabalho: aparece na fila de quem prospecta. */
    public function emAberto(): bool
    {
        return in_array($this, [self::Novo, self::Atendendo, self::Agendado], true);
    }

    /** Sem data marcada, "Agendado" nao diz nada a quem abre a tela amanha. */
    public function exigeData(): bool
    {
        return $this === self::Agendado;
    }

    /**
     * O vendedor registra o andamento da propria conversa, e nada alem dela.
     *
     * "Nao atender" fica de fora porque e decisao da casa, e esconde o lead da
     * distribuicao. "Virou cliente" tambem: quem marca e a conversao, quando o
     * cadastro do cliente e gravado de verdade. Deixar os dois na mao de quem
     * prospecta criaria lead convertido sem cliente do outro lado.
     *
     * @return array<string, string>
     */
    public static function doVendedor(): array
    {
        return array_reduce(
            [self::Novo, self::Atendendo, self::Agendado, self::Recusado],
            fn (array $mapa, self $caso) => $mapa + [$caso->value => $caso->rotulo()],
            [],
        );
    }

    /** @return array<string, string> valor => rotulo, para select e filtro */
    public static function rotulos(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $mapa, self $caso) => $mapa + [$caso->value => $caso->rotulo()],
            [],
        );
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tentar(?string $valor): ?self
    {
        return $valor === null ? null : self::tryFrom($valor);
    }
}
