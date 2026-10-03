<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Que papel o contato tem em qual frente: a mesma pessoa pode ser cliente do One e negocio do Sales. */
class Vinculo extends Model
{
    protected $fillable = ['contato_id', 'papel', 'entidade_tipo', 'entidade_id'];

    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }

    public function entidade(): MorphTo
    {
        return $this->morphTo('entidade', 'entidade_tipo', 'entidade_id');
    }
}
