<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma tiragem de plaquinhas.
 *
 * Existe para o pacote de arquivos poder ser refeito depois. A arte se perde,
 * o HD queima, a grafica pede de novo, e a pagina do lote reconstroi o ZIP
 * inteiro a partir dos codigos: nenhuma imagem precisa ser guardada, porque o
 * desenho e determinado pelo codigo.
 */
class LoteEtiqueta extends Model
{
    protected $table = 'lotes_etiquetas';

    protected $fillable = ['codigo', 'titulo', 'quantidade', 'tipo', 'observacao', 'staff_id'];

    /** As campanhas que uma conta pode ver: as que tem codigo dela. */
    public function scopeVisiveis(\Illuminate\Database\Eloquent\Builder $consulta): \Illuminate\Database\Eloquent\Builder
    {
        if (\App\Support\Dono::veTudo()) {
            return $consulta;
        }

        return $consulta->whereHas('etiquetas', fn ($etiquetas) => \App\Support\Dono::limitar($etiquetas));
    }

    protected function casts(): array
    {
        return ['quantidade' => 'integer'];
    }

    public function etiquetas(): HasMany
    {
        return $this->hasMany(Etiqueta::class, 'lote_id')->orderBy('sequencia');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** Nome da pasta dentro do ZIP, e do CSV que o Corel le. */
    public function pasta(): string
    {
        return 'lote-'.strtolower($this->codigo);
    }
}
