<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma versão publicável de termo, contrato ou política. */
class DocumentoLegal extends Model
{
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'titulo', 'tipo', 'versao', 'conteudo', 'exige_aceite', 'ativo',
        'para_empresa', 'para_operador', 'para_vendedor',
    ];

    protected function casts(): array
    {
        return [
            'exige_aceite' => 'boolean', 'ativo' => 'boolean',
            'para_empresa' => 'boolean', 'para_operador' => 'boolean', 'para_vendedor' => 'boolean',
        ];
    }

    /** Documentos ativos de um publico: empresa, operador ou vendedor. */
    public function scopePara($consulta, string $publico)
    {
        return $consulta->where('ativo', true)->where('para_'.$publico, true);
    }

    public function aceites(): HasMany
    {
        return $this->hasMany(AceiteDocumento::class, 'documento_id');
    }

    public function hashConteudo(): string
    {
        return hash('sha256', $this->conteudo);
    }
}
