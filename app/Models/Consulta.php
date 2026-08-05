<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma consulta feita por uma empresa, com o preco congelado.
 *
 * `preco_cents` e `custo_cents` sao copias do catalogo no instante da consulta,
 * e nao referencias. Reajuste posterior nao mexe nesta linha, que e o que
 * permite reprecificar o catalogo sem reescrever o passado.
 */
class Consulta extends Model
{
    use HasFactory;

    protected $table = 'consultas';

    protected $fillable = [
        'cliente_id', 'servico_id', 'competencia', 'preco_cents', 'custo_cents',
    ];

    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'servico_id' => 'integer',
            'preco_cents' => 'integer',
            'custo_cents' => 'integer',
        ];
    }

    /** Competencia de uma data, no formato que o fechamento agrupa. */
    public static function competenciaDe(?\DateTimeInterface $momento = null): string
    {
        return ($momento ?? now())->format('Y-m');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
