<?php

namespace App\Models;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quem vende parcelado pelo Avalia 360.
 *
 * A subconta no provedor e o que separa produtor cadastrado de produtor que
 * pode vender: sem `asaas_wallet_id` nao ha para onde o split mandar a parte
 * dele, e a venda ficaria com o dinheiro na conta errada.
 */
class Produtor extends Model
{
    protected $table = 'produtores';

    protected $fillable = [
        'nome', 'documento', 'whatsapp', 'email', 'situacao',
        'asaas_account_id', 'asaas_wallet_id', 'asaas_api_key',
        'interessado_cobranca_id', 'aprovado_em',
    ];

    protected function casts(): array
    {
        return [
            'documento' => 'encrypted',
            'whatsapp' => 'encrypted',
            'asaas_api_key' => 'encrypted',
            'aprovado_em' => 'datetime',
        ];
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto360::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido360::class);
    }

    /** Pode publicar oferta e receber: aprovado e com subconta pronta. */
    public function podeVender(): bool
    {
        return $this->situacao === 'aprovado' && filled($this->asaas_wallet_id);
    }

    public function documentoRotulo(): string
    {
        return Documento::formatar($this->documento);
    }
}
