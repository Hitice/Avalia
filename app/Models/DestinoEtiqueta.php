<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para onde uma plaquinha apontou, e por quanto tempo.
 *
 * A linha aberta (`vigorou_ate` nulo) e a atual, e a coluna `destino` da
 * etiqueta e copia dela: o redirecionamento nao pode depender de um join, e a
 * historia nao pode depender de ninguem lembrar de escrever.
 */
class DestinoEtiqueta extends Model
{
    protected $table = 'destinos_etiqueta';

    protected $fillable = ['etiqueta_id', 'destino', 'vigorou_de', 'vigorou_ate', 'staff_id'];

    protected function casts(): array
    {
        return [
            'vigorou_de' => 'datetime',
            'vigorou_ate' => 'datetime',
        ];
    }

    public function etiqueta(): BelongsTo
    {
        return $this->belongsTo(Etiqueta::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopeVigente(Builder $consulta): Builder
    {
        return $consulta->whereNull('vigorou_ate');
    }
}
