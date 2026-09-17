<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** O que o produtor vende. As condicoes de venda ficam na oferta. */
class Produto360 extends Model
{
    use SoftDeletes;

    protected $table = 'produtos_360';

    protected $fillable = ['produtor_id', 'nome', 'descricao', 'valor_cents', 'dias_arrependimento', 'ativo'];

    protected function casts(): array
    {
        return ['valor_cents' => 'integer', 'dias_arrependimento' => 'integer', 'ativo' => 'boolean'];
    }

    public function produtor(): BelongsTo
    {
        return $this->belongsTo(Produtor::class);
    }

    public function ofertas(): HasMany
    {
        return $this->hasMany(Oferta360::class, 'produto_360_id');
    }
}
