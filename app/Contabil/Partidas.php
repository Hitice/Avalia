<?php

namespace App\Contabil;

use App\Exceptions\Recusa;
use App\Models\ContaFinanceira;

/**
 * As pernas de um lancamento, ja conferidas. Debito positivo, credito negativo.
 *
 * A invariante vivia dentro de `RegistrarLancamento`, que traduz natureza em
 * duas pernas; evento de produto tem seis. Copiar a conferencia daria dois
 * lugares com direito de dizer que o razao fecha.
 */
final class Partidas
{
    /** @param array<int, int> $pernas conta_id => valor com sinal */
    private function __construct(public readonly array $pernas) {}

    /**
     * Pelo id da conta, para quem ja resolveu.
     *
     * @param  array<int, int>  $pernas
     */
    public static function de(array $pernas): self
    {
        $soma = array_sum($pernas);

        if ($soma !== 0) {
            throw new Recusa('Lançamento desbalanceado: as partidas somam '.$soma.' em vez de zero.');
        }

        if (count($pernas) < 2) {
            throw new Recusa('Lançamento precisa de pelo menos duas partidas.');
        }

        return new self($pernas);
    }

    /**
     * Pelo codigo da conta. Perna de valor zero e descartada: venda de socio nao
     * gera comissao, e o extrato nao ganha nada com duas partidas vazias.
     *
     * @param  array<string, int>  $pernas
     */
    public static function porCodigo(array $pernas): self
    {
        $pernas = array_filter($pernas, fn (int $cents) => $cents !== 0);

        $contas = ContaFinanceira::whereIn('codigo', array_keys($pernas))
            ->pluck('id', 'codigo');

        $porId = [];

        foreach ($pernas as $codigo => $cents) {
            $id = $contas[$codigo] ?? throw new Recusa("A conta {$codigo} não está cadastrada.");

            // Somado, e nao sobrescrito: duas pernas podem cair na mesma conta,
            // como custo e comissao saindo do caixa no mesmo evento.
            $porId[$id] = ($porId[$id] ?? 0) + $cents;
        }

        return self::de(array_filter($porId, fn (int $cents) => $cents !== 0));
    }

    /** O lado devedor, que e o valor que se mostra como "quanto foi". */
    public function debitoCents(): int
    {
        return array_sum(array_filter($this->pernas, fn (int $c) => $c > 0));
    }
}
