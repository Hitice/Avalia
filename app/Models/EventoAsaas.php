<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoAsaas extends Model
{
    protected $table = 'eventos_asaas';

    protected $fillable = ['evento_externo', 'tipo', 'cobranca_asaas_id', 'payload', 'recebido_em', 'processado_em', 'erro'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'recebido_em' => 'datetime', 'processado_em' => 'datetime'];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(CobrancaAsaas::class, 'cobranca_asaas_id');
    }
}
