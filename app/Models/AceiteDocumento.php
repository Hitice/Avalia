<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prova do texto e da versão aceitos por uma empresa. */
class AceiteDocumento extends Model
{
    use HasFactory;

    protected $table = 'aceites_documento';

    protected $fillable = [
        'documento_id', 'cliente_id', 'operador_id', 'staff_id', 'responsavel', 'versao', 'hash_conteudo',
        'ip_address', 'user_agent', 'aceito_em',
    ];

    protected function casts(): array
    {
        return ['documento_id' => 'integer', 'cliente_id' => 'integer', 'aceito_em' => 'datetime'];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class, 'documento_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
