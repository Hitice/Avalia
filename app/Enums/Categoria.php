<?php

namespace App\Enums;

/**
 * Familia de servico do catalogo.
 *
 * Enum e nao constante de array: o tipo passa a ser garantido pelo PHP em
 * parametro, retorno e cast do model, e o rotulo anda junto do valor em vez de
 * viver num mapa paralelo que alguem esquece de atualizar.
 */
enum Categoria: string
{
    case Credito = 'credito';
    case Veicular = 'veicular';

    public function rotulo(): string
    {
        return match ($this) {
            self::Credito => 'Crédito',
            self::Veicular => 'Veicular',
        };
    }

    /**
     * Familia cujos numeros comerciais nao aparecem ainda.
     *
     * Veicular esta no catalogo porque ja foi precificado, mas o contrato com o
     * fornecedor nao foi fechado: preco, custo e margem sao estimativa, e
     * estimativa exibida sem aviso vira proposta. A linha continua visivel, com
     * cadeado, para a administracao saber que existe e o que falta liberar.
     *
     * Fechado o contrato, esta regra cai em um lugar so.
     */
    public function suprimida(): bool
    {
        return $this === self::Veicular;
    }

    /** @return array<string, string> valor => rotulo, para select e aba */
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
