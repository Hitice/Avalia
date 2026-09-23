<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha do razao do Avalia Gestor. Nasce e nunca muda.
 *
 * `UPDATED_AT` desligado porque a tabela nao tem a coluna, e nao tem de
 * proposito: lancamento que se atualiza deixa de explicar o saldo que alguem
 * ja conferiu. Corrigir e lancar o contrario.
 */
class Lancamento360 extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'lancamentos_360';

    protected $fillable = [
        'pedido_360_id', 'parcela_360_id', 'beneficiario_id', 'tipo', 'valor_cents',
        'ocorrido_em', 'evento_asaas_id', 'descricao',
    ];

    protected function casts(): array
    {
        return ['valor_cents' => 'integer', 'ocorrido_em' => 'datetime'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido360::class, 'pedido_360_id');
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela360::class, 'parcela_360_id');
    }

    /** Quem recebeu, quando a linha e de repasse. */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Produtor::class, 'beneficiario_id');
    }
}
