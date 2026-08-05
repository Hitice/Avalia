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
