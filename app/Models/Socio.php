<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Quem divide a empresa. Relacao societaria, nao conta de acesso. */
class Socio extends Model
{
    protected $fillable = ['nome', 'staff_id', 'participacao_bps', 'ativo'];

    protected function casts(): array
    {
        return ['participacao_bps' => 'integer', 'ativo' => 'boolean'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function contas(): HasMany
    {
        return $this->hasMany(ContaFinanceira::class);
    }
}
