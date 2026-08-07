<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Operador;
use App\Models\Staff;
use Illuminate\Support\Facades\URL;

/**
 * O convite de acesso: um link assinado para a pessoa definir a propria senha.
 *
 * Senha nao viaja por e-mail. Caixa de entrada e arquivo permanente, e-mail
 * atravessa servidores de terceiros, e a mesma senha reaparece em outros
 * servicos. O convite manda so um endereco: quem o abre dentro do prazo define
 * a senha que quiser, e o sistema nunca a conhece em claro.
 *
 * O link morre duas vezes: pelo prazo (48h, assinatura temporaria) e pelo uso.
 * O carimbo derivado da senha atual entra na assinatura; definida a senha
 * nova, o carimbo muda e todo link antigo deixa de validar. Reenviar convite e
 * simplesmente gerar outro link.
 */
final class Convite
{
    public const HORAS_DE_VALIDADE = 48;

    /** @param  Staff|Cliente  $conta */
    public static function link(object $conta, string $guarda): string
    {
        return URL::temporarySignedRoute('senha.definir', now()->addHours(self::HORAS_DE_VALIDADE), [
            'guarda' => $guarda,
            'id' => $conta->getKey(),
            'k' => self::carimbo($conta),
        ]);
    }

    /**
     * Fragmento derivado da senha atual. Nao revela nada (12 hex de um sha256
     * do hash, nao da senha), e muda junto com ela: e o que mata o link usado.
     *
     * @param  Staff|Cliente  $conta
     */
    public static function carimbo(object $conta): string
    {
        return substr(hash('sha256', (string) $conta->senha), 0, 12);
    }

    /** @return Staff|Cliente|Operador|null */
    public static function conta(string $guarda, int $id): ?object
    {
        return match ($guarda) {
            'staff' => Staff::find($id),
            'empresa' => Cliente::find($id),
            'operador' => Operador::find($id),
            default => null,
        };
    }
}
