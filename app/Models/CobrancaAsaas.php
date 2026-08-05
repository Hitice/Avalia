<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaAsaas extends Model
{
    protected $table = 'cobrancas_asaas';

    protected $fillable = [
        'cliente_id', 'fatura_id', 'adesao_id', 'parcela', 'asaas_charge_id', 'situacao', 'tipo_cobranca',
        'valor_cents', 'vencimento', 'invoice_url', 'bank_slip_url', 'pix_copia_cola', 'resposta',
    ];

    protected function casts(): array
    {
        return ['valor_cents' => 'integer', 'vencimento' => 'date', 'resposta' => 'array'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function adesao(): BelongsTo
    {
        return $this->belongsTo(Adesao::class);
    }
}
