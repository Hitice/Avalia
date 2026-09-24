<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantas leituras a plaquinha teve num dia.
 *
 * Agregado, e nao registro de cada leitura: a pergunta do cliente e "quantas
 * no mes", e uma linha por encostada de celular encheria a base numa
 * hospedagem compartilhada sem responder nada melhor. Tambem nao guarda IP,
 * que e dado pessoal e nao entra numa contagem.
 */
class AcessoEtiqueta extends Model
{
    protected $table = 'acessos_etiqueta';

    /** A linha e um contador somado por upsert, e nao um registro datado. */
    public $timestamps = false;

    protected $fillable = ['etiqueta_id', 'dia', 'total'];

    protected function casts(): array
    {
        return [
            'dia' => 'date',
            'total' => 'integer',
        ];
    }

    public function etiqueta(): BelongsTo
    {
        return $this->belongsTo(Etiqueta::class);
    }
}
