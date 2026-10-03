<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A linha do tempo do contato: cadastro, venda, fatura, nota. */
class Interacao extends Model
{
    protected $table = 'interacoes';

    protected $fillable = ['contato_id', 'tipo', 'descricao', 'ocorrido_em', 'staff_id'];

    protected function casts(): array
    {
        return ['ocorrido_em' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
