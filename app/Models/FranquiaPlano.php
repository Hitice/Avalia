<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantas consultas de um servico ja vem pagas na mensalidade do plano.
 *
 * A franquia e contada em QUANTIDADE, nao em reais: o consumo minimo e que e
 * valor. Sao dois controles diferentes e independentes na mesma fatura.
 */
class FranquiaPlano extends Model
{
    use HasFactory;

    protected $table = 'franquias_plano';

    protected $fillable = ['plano_id', 'servico_id', 'quantidade'];

    protected function casts(): array
    {
        return [
            'plano_id' => 'integer',
            'servico_id' => 'integer',
            'quantidade' => 'integer',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
