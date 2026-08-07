<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro imutável de ação administrativa, financeira ou de aceite. */
class Auditoria extends Model
{
    use HasFactory;

    protected $table = 'auditoria';

    protected $fillable = [
        'staff_id', 'acao', 'entidade_tipo', 'entidade_id', 'entidade_rotulo', 'dados',
        'ip_address', 'user_agent', 'ocorreu_em', 'resumo_anterior', 'resumo',
    ];

    protected function casts(): array
    {
        return ['staff_id' => 'integer', 'entidade_id' => 'integer', 'dados' => 'array', 'ocorreu_em' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
