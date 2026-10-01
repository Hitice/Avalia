<?php

namespace App\Enums;

/**
 * Onde o negocio esta no atendimento de marketing.
 *
 * Nao e o funil de leads: lead e quem talvez compre, negocio e quem ja e
 * cliente. Aqui a pergunta e o que falta entregar para ele.
 */
enum SituacaoNegocio: string
{
    case Recebido = 'recebido';
    case EmCadastro = 'em_cadastro';
    case Publicado = 'publicado';
    case Pendente = 'pendente';
    case Recusado = 'recusado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Recebido => 'Recebido',
            self::EmCadastro => 'Em cadastro',
            self::Publicado => 'Publicado',

            // "Pendente" e do lado do cliente, e nao do nosso: falta foto, falta
            // comprovante de endereco, falta ele confirmar o codigo que o Google
            // manda pelo correio. Separado de "em cadastro" porque a acao e de
            // quem cobrar, e nao de quem executa.
            self::Pendente => 'Esperando o cliente',

            self::Recusado => 'Recusado',
        };
    }

    public static function tentar(?string $valor): ?self
    {
        return $valor === null ? null : self::tryFrom($valor);
    }

    /** @return array<string, string> */
    public static function rotulos(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->rotulo()])->all();
    }
}
