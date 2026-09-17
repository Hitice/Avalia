<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma parcela do carne, ou a entrada quando o numero e zero.
 *
 * A situacao segue o que o provedor informa por webhook, e nao o calendario:
 * parcela vence no papel, mas so vira `vencida` quando o provedor diz que
 * venceu sem pagamento. Calendario local e relogio de servidor decidindo
 * dinheiro, que e como nasce divergencia com o extrato.
 */
class Parcela360 extends Model
{
    protected $table = 'parcelas_360';

    protected $fillable = [
        'pedido_360_id', 'numero', 'valor_cents', 'vencimento',
        'situacao', 'paga_em', 'cobranca_asaas_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'valor_cents' => 'integer',
            'vencimento' => 'date',
            'paga_em' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido360::class, 'pedido_360_id');
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(CobrancaAsaas::class, 'cobranca_asaas_id');
    }

    public function ehEntrada(): bool
    {
        return $this->numero === 0;
    }

    public function scopeEmAberto($consulta)
    {
        return $consulta->whereIn('situacao', ['aberta', 'vencida']);
    }
}
