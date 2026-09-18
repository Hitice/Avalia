<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Uma forma de vender um produto: valor, parcelas e entrada.
 *
 * O slug nasce aqui, e nao no controller, porque ele e parte da oferta: o link
 * ja entregue a um cliente nao pode mudar de significado se alguem editar o
 * titulo.
 */
class Oferta360 extends Model
{
    use SoftDeletes;

    protected $table = 'ofertas_360';

    protected $fillable = [
        'produto_360_id', 'titulo', 'tipo', 'valor_cents', 'parcelas', 'meses',
        'entrada_cents', 'entrada_em_dias', 'slug', 'ativa',
    ];

    protected function casts(): array
    {
        return [
            'valor_cents' => 'integer',
            'parcelas' => 'integer',
            'meses' => 'integer',
            'entrada_cents' => 'integer',
            'entrada_em_dias' => 'integer',
            'ativa' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $oferta) {
            $oferta->slug ??= Str::lower(Str::random(10));
        });
    }

    /** Servico cobrado todo mes, em vez de venda dividida em parcelas. */
    public function ehMensal(): bool
    {
        return $this->tipo === 'mensal';
    }

    /**
     * O que o cliente paga por mes.
     *
     * Na mensalidade e o proprio valor da oferta: nao existe total a dividir,
     * existe uma cobranca que se repete.
     */
    public function mensalidadeCents(): int
    {
        return $this->ehMensal() ? $this->valor_cents : $this->valorDaParcela();
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto360::class, 'produto_360_id');
    }

    /** O que o cliente paga em cada parcela depois da entrada. */
    public function valorDaParcela(): int
    {
        if ($this->parcelas < 1) {
            return 0;
        }

        // A divisao inteira sobra centavos, e a sobra vai na primeira parcela:
        // e o que o mercado faz, e impede que a soma das parcelas fique um
        // centavo abaixo do total contratado.
        return intdiv($this->valor_cents - $this->entrada_cents, $this->parcelas);
    }

    public function sobraDaDivisao(): int
    {
        return ($this->valor_cents - $this->entrada_cents) - ($this->valorDaParcela() * $this->parcelas);
    }
}
