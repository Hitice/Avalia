<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Para onde cada produto leva a sessao de quem esta logado.
 *
 * A resposta era privada no `LoginController` e mandava o produtor para o painel
 * do cliente. A vitrine precisa dela, e duas copias divergem.
 */
final class Porta
{
    /** Os guards da casa, na ordem em que se pergunta por eles. */
    public const GUARDAS = ['staff', 'empresa', 'produtor'];

    /** O painel de quem esta logado agora, ou null quando ninguem esta. */
    public static function painelDaSessao(): ?string
    {
        foreach (self::GUARDAS as $guarda) {
            if (Auth::guard($guarda)->check()) {
                return self::painelDe($guarda);
            }
        }

        return null;
    }

    /** O painel de um guard. */
    public static function painelDe(string $guarda): string
    {
        return match ($guarda) {
            'staff' => route('painel'),
            'produtor' => route('produtor.painel'),
            default => route('empresa.painel'),
        };
    }

    /**
     * A entrada de UM produto para a sessao atual, ou null.
     *
     * Null quando nao ha sessao, e quando a sessao nao tem acesso ao produto. A
     * vitrine abre a porta no primeiro caso e esconde o botao no segundo.
     *
     * O Gestor nao tem tela do lado da casa: ele e operado pelo produtor. Estado
     * do sistema, e nao esquecimento.
     */
    public static function entradaDe(string $produto): ?string
    {
        return match ($produto) {
            'vendas' => match (true) {
                Auth::guard('staff')->check() => route('sales.inicio'),
                self::painelDaSessao() !== null => route('etiquetas.index'),
                default => null,
            },

            'credito' => match (true) {
                Auth::guard('staff')->check() => route('painel'),
                Auth::guard('empresa')->check() => route('empresa.painel'),
                default => null,
            },

            'cobranca' => Auth::guard('produtor')->check() ? route('produtor.painel') : null,

            default => null,
        };
    }
}
