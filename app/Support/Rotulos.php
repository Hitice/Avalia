<?php

namespace App\Support;

use App\Models\Fatura;

/**
 * Como cada situacao se chama na tela.
 *
 * As telas vinham imprimindo o valor gravado no banco: "liquidado", "vencido",
 * "inadimplente", em minusculas. Sao nomes escolhidos para o codigo, nao para
 * quem le. O cliente que abre a fatura precisa entender "Paga" ou "Em aberto";
 * "liquidado" e vocabulario de quem escreveu a coluna.
 *
 * O mapa vive aqui e nao em cada Blade porque a mesma situacao aparece na tela
 * do cliente, na do vendedor e na da administracao. Tres listas soltas viram
 * tres nomes diferentes para o mesmo estado no primeiro ajuste.
 *
 * A cor acompanha o nome pelo mesmo motivo: fatura vencida vermelha em uma tela
 * e cinza em outra ensina o operador a nao confiar na cor.
 */
final class Rotulos
{
    /** @var array<string, array{rotulo: string, etiqueta: string}> */
    private const EMPRESA = [
        'ativo' => ['rotulo' => 'Ativa', 'etiqueta' => 'etiqueta-sucesso'],
        'inadimplente' => ['rotulo' => 'Suspensa por débito', 'etiqueta' => 'etiqueta-alerta'],
        'bloqueado' => ['rotulo' => 'Bloqueada', 'etiqueta' => 'etiqueta-erro'],
        'inativo' => ['rotulo' => 'Encerrada', 'etiqueta' => 'etiqueta-neutra'],
    ];

    /** @var array<string, array{rotulo: string, etiqueta: string}> */
    private const FATURA = [
        Fatura::PAGAMENTO_PENDENTE => ['rotulo' => 'Em aberto', 'etiqueta' => 'etiqueta-neutra'],
        Fatura::PAGAMENTO_VENCIDO => ['rotulo' => 'Vencida', 'etiqueta' => 'etiqueta-alerta'],
        Fatura::PAGAMENTO_LIQUIDADO => ['rotulo' => 'Paga', 'etiqueta' => 'etiqueta-sucesso'],
        Fatura::PAGAMENTO_CANCELADO => ['rotulo' => 'Cancelada', 'etiqueta' => 'etiqueta-erro'],
        Fatura::PAGAMENTO_ESTORNADO => ['rotulo' => 'Estornada', 'etiqueta' => 'etiqueta-erro'],
    ];

    public static function empresa(?string $situacao): string
    {
        return self::EMPRESA[$situacao]['rotulo'] ?? 'Não definida';
    }

    public static function empresaEtiqueta(?string $situacao): string
    {
        return self::EMPRESA[$situacao]['etiqueta'] ?? 'etiqueta-neutra';
    }

    public static function fatura(?string $situacao): string
    {
        return self::FATURA[$situacao]['rotulo'] ?? 'Não definida';
    }

    public static function faturaEtiqueta(?string $situacao): string
    {
        return self::FATURA[$situacao]['etiqueta'] ?? 'etiqueta-neutra';
    }

    /** Opcoes do campo de situacao da empresa, na ordem do ciclo de vida. */
    public static function situacoesDaEmpresa(): array
    {
        return array_map(fn (array $item) => $item['rotulo'], self::EMPRESA);
    }

    /**
     * Espera do cliente, em segundos.
     *
     * O banco guarda milissegundos porque e a unidade que o fornecedor devolve.
     * Ninguem decide nada com "847 ms": a pergunta de quem opera e se o cliente
     * espera muito, e isso se mede em segundos.
     */
    public static function espera(?int $milissegundos): string
    {
        if ($milissegundos === null) {
            return 'Sem registro';
        }

        return number_format($milissegundos / 1000, 1, ',', '.').' s';
    }
}
