<?php

namespace App\Enums;

/**
 * O que a plaquinha e, no banco.
 *
 * Quatro estados, e nenhum deles e "vencida". Vencimento se calcula da data,
 * e estado calculado nao sai de sincronia: gravar "vencida" exigiria alguem
 * rodando todo dia para virar a chave, e no dia em que esse alguem falhasse a
 * plaquinha continuaria ativa no banco e morta na data.
 *
 * Nao existe exclusao, pela regra da casa: plaquinha que quebrou ou cujo
 * cliente saiu vira `baixada`, e o codigo dela nunca volta ao bolo do sorteio.
 * Reciclar codigo mandaria a freguesia do cliente antigo para a loja de um
 * estranho.
 */
enum SituacaoEtiqueta: string
{
    /** Gerada e impressa, ainda sem destino. E o estado do estoque. */
    case EmBranco = 'em_branco';

    /** Vendida e apontando para algum lugar. */
    case Ativa = 'ativa';

    /** Desligada a mao, por decisao nossa ou do cliente. */
    case Suspensa = 'suspensa';

    /** Fim de linha: placa quebrada, cliente saiu. Nao redireciona mais. */
    case Baixada = 'baixada';

    public function rotulo(): string
    {
        return match ($this) {
            self::EmBranco => 'Em branco',
            self::Ativa => 'Ativa',
            self::Suspensa => 'Suspensa',
            // "Encerrada", e nao "Baixada". No banco a coluna continua
            // `baixada`, que e o termo do negocio, mas na tela "baixar" e lido
            // como fazer download, e o botao que tira a plaquinha de
            // circulacao para sempre nao pode ser confundido com o que salva
            // um arquivo.
            self::Baixada => 'Encerrada',
        };
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
