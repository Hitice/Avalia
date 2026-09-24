<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um ano de servico pago.
 *
 * Guarda o valor COBRADO, e nao o de tabela: a renovacao do ano que vem pode
 * custar outro preco, e o historico precisa continuar explicando o que foi
 * cobrado de quem.
 */
class RenovacaoEtiqueta extends Model
{
    protected $table = 'renovacoes_etiqueta';

    protected $fillable = ['etiqueta_id', 'valor_cents', 'de', 'ate', 'staff_id'];

    protected function casts(): array
    {
        return [
            'valor_cents' => 'integer',
            'de' => 'date',
            'ate' => 'date',
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
}
