<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quanto custa um servico, numa faixa, dentro de uma versao do catalogo.
 *
 * O preco nao vive no servico: o mesmo relatorio sai por R$ 2,09 sem consumo
 * minimo e por R$ 0,98 na faixa de R$ 5.000. Quem escolhe a coluna e o plano
 * do cliente.
 */
class Preco extends Model
{
    use HasFactory;

    protected $table = 'precos';

    protected $fillable = [
        'catalogo_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents',
    ];

    protected function casts(): array
    {
        return [
            'catalogo_id' => 'integer',
            'servico_id' => 'integer',
            'consumo_minimo_cents' => 'integer',
            'preco_cents' => 'integer',
            'custo_cents' => 'integer',
        ];
    }

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    /**
     * Margem bruta da Avalia nesta linha, ou null enquanto o custo do
     * fornecedor nao estiver cadastrado. Nunca vai para tela de cliente.
     */
    public function margemCents(): ?int
    {
        return $this->custo_cents === null
            ? null
            : $this->preco_cents - $this->custo_cents;
    }

    public function getPrecoAttribute(): string
    {
        return Dinheiro::brl($this->preco_cents);
    }

    public function getCustoAttribute(): ?string
    {
        return $this->custo_cents === null ? null : Dinheiro::brl($this->custo_cents);
    }

    public function getFaixaAttribute(): string
    {
        return $this->consumo_minimo_cents === 0
            ? 'Sem mínimo'
            : Dinheiro::brl($this->consumo_minimo_cents);
    }
}
