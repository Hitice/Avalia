<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Taxa de adesao do contrato, parcelavel e rateada meio a meio.
 *
 * O nome da tabela e explicito porque o plural que o Eloquent monta sozinho
 * para "Adesao" e "adesaos", e a migration criou "adesoes". Sem esta linha a
 * ficha da empresa quebra com "no such table".
 */
class Adesao extends Model
{
    protected $table = 'adesoes';

    /**
     * As parcelas de um valor, em centavos, somando exatamente o total.
     *
     * Dividir dinheiro em partes iguais quase nunca fecha: R$ 1.000,00 em tres
     * da 333,33 tres vezes, que sao R$ 999,99. Truncar perde o centavo e
     * arredondar cobra um a mais, e nos dois casos a soma das cobrancas deixa
     * de bater com o que o cliente assinou.
     *
     * A sobra vai na PRIMEIRA parcela: e a que o cliente confere na assinatura,
     * e adiar a diferenca faria a ultima cobranca destoar meses depois, quando
     * ninguem lembra por que.
     *
     * @return list<int>
     */
    public static function parcelasDe(int $totalCents, int $vezes): array
    {
        if ($totalCents <= 0) {
            return [];
        }

        $vezes = max(1, $vezes);
        $base = intdiv($totalCents, $vezes);
        $sobra = $totalCents - $base * $vezes;

        return array_map(
            fn (int $posicao) => $posicao === 0 ? $base + $sobra : $base,
            range(0, $vezes - 1),
        );
    }

    protected $fillable = ['cliente_id', 'valor_cents', 'parcelas', 'valor_parcela_cents', 'vendedor_cents', 'avalia_cents', 'liquidada_em'];

    protected function casts(): array
    {
        return ['valor_cents' => 'integer', 'parcelas' => 'integer', 'valor_parcela_cents' => 'integer', 'vendedor_cents' => 'integer', 'avalia_cents' => 'integer', 'liquidada_em' => 'datetime'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(CobrancaAsaas::class);
    }
}
