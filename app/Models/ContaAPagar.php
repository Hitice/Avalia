<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaAPagar extends Model
{
    protected $table = 'contas_a_pagar';

    protected $fillable = ['descricao', 'fornecedor', 'categoria_id', 'valor_cents', 'vence_em', 'pago_em', 'lancamento_id', 'pagamento_id', 'staff_id'];

    protected function casts(): array
    {
        return ['valor_cents' => 'integer', 'vence_em' => 'date', 'pago_em' => 'datetime'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'categoria_id');
    }

    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->whereNull('pago_em');
    }
}
