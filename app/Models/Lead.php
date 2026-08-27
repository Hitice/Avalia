<?php

namespace App\Models;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa da base de prospeccao, antes de existir contrato.
 *
 * Nao e conta: lead nao entra na plataforma, nao tem senha e nao consulta nada.
 * E cadastro de terceiro que a Avalia guarda para trabalhar a venda, e por isso
 * toda saida dele em arquivo vai para a trilha de auditoria.
 */
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'codigo', 'nome', 'cnpj', 'cidade', 'uf', 'telefone', 'email', 'origem', 'ativo', 'observacao',
    ];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /**
     * Os vendedores com quem a administracao compartilhou este lead.
     *
     * Muitos para muitos porque a mesma empresa pode ser trabalhada por mais de
     * um vendedor, e porque a data de cada compartilhamento importa.
     */
    public function vendedores(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'lead_staff', 'lead_id', 'staff_id')
            ->withPivot(['compartilhado_em', 'compartilhado_por']);
    }

    /** Cidade e UF juntas, como a tela mostra. */
    public function cidadeRotulo(): string
    {
        return trim($this->cidade.($this->cidade && $this->uf ? '/' : '').$this->uf);
    }

    public function cnpjRotulo(): string
    {
        return Documento::formatarCnpj($this->cnpj);
    }

    /**
     * Tem por onde ligar ou escrever.
     *
     * Lead sem telefone e sem e-mail nao da trabalho ao vendedor: ele precisa
     * de enriquecimento antes, e por isso a tela sabe separar os dois grupos.
     */
    public function temContato(): bool
    {
        return $this->telefone !== null || $this->email !== null;
    }
}
