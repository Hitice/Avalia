<?php

namespace App\Contabil;

use App\Models\Staff;
use Illuminate\Support\Collection;

/**
 * Quem divide o lucro das plaquinhas, resolvido de config em contas.
 *
 * Saiu do `VendasPlaquinhasController` porque o lastro precisa da mesma lista:
 * socio nao comissiona, e a lista decide isso.
 *
 * A ORDEM do config e a ordem da divisao, e e o que mantem o centavo impar
 * sempre na mesma pessoa.
 */
final class SociosDaPlaquinha
{
    /** @return array{contas: Collection<int, Staff>, ids: list<int>, ausentes: list<string>} */
    public static function resolver(): array
    {
        $emails = collect(config('etiquetas.socios'))
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter();

        // withTrashed porque socio que saiu da equipe nao deixa de ter recebido:
        // sem isso, a venda dele voltaria a comissionar retroativamente.
        $achadas = Staff::withTrashed()
            ->whereIn('email', $emails->all())
            ->get(['id', 'nome', 'email'])
            ->keyBy(fn (Staff $s) => mb_strtolower($s->email));

        $contas = $emails->map(fn (string $email) => $achadas->get($email))->filter()->values();

        return [
            'contas' => $contas,
            'ids' => $contas->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'ausentes' => $emails->reject(fn (string $email) => $achadas->has($email))->values()->all(),
        ];
    }
}
