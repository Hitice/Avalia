<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Detalhe imutável da franquia e do excedente de um serviço na fatura. */
class ItemFatura extends Model
{
    use HasFactory;

    protected $table = 'itens_fatura';

    protected $fillable = [
        'fatura_id', 'servico_id', 'servico_nome', 'quantidade',
        'quantidade_franquia', 'quantidade_excedente', 'valor_bruto_cents',
        'valor_franquia_cents', 'valor_excedente_cents', 'custo_cents',
    ];

    protected function casts(): array
    {
        return array_fill_keys([
            'fatura_id', 'servico_id', 'quantidade', 'quantidade_franquia',
            'quantidade_excedente', 'valor_bruto_cents', 'valor_franquia_cents',
            'valor_excedente_cents', 'custo_cents',
        ], 'integer');
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
