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

    protected $fillable = ['titulo', 'tipo', 'versao', 'conteudo', 'exige_aceite', 'ativo'];

    protected function casts(): array
    {
        return ['exige_aceite' => 'boolean', 'ativo' => 'boolean'];
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
