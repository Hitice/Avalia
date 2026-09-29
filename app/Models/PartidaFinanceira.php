<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma perna do lancamento. Positivo debita, negativo credita.
 *
 * Nao se atualiza e nao se apaga: corrigir e estornar. Sem `updated_at`, como o
 * razao do 360, porque linha que muda deixa de explicar o saldo que alguem ja
 * conferiu.
 */
class PartidaFinanceira extends Model
{
    protected $table = 'partidas_financeiras';

    public $timestamps = false;

    protected $fillable = ['lancamento_id', 'conta_id', 'valor_cents'];

    protected function casts(): array
    {
        return ['valor_cents' => 'integer', 'created_at' => 'datetime'];
    }

    public function lancamento(): BelongsTo
    {
        return $this->belongsTo(LancamentoFinanceiro::class, 'lancamento_id');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'conta_id');
    }
}
