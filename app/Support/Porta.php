<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Para onde a sessao de quem esta logado leva.
 *
 * Existe porque a resposta era privada dentro do `LoginController`, e os cartoes
 * da vitrine precisam da mesma: o botao "Entrar" leva direto quem ja tem sessao,
 * e abre a porta para quem nao tem. Escrita duas vezes, o dia em que um guard
 * novo entrasse deixaria a vitrine mandando o produtor para o painel do cliente.
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
}
