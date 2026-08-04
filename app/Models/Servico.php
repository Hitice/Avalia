<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um servico vendavel do catalogo.
 *
 * Identidade apenas: nome, codigo e categoria. Quanto custa e assunto de
 * Preco, porque o valor depende da faixa contratada e da versao vigente.
 */
class Servico extends Model
{
    use HasFactory;

    public const CATEGORIAS = [
        'credito' => 'Crédito',
        'veicular' => 'Veicular',
    ];

    protected $fillable = ['codigo', 'nome', 'categoria', 'ativo', 'exige_liberacao'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'exige_liberacao' => 'boolean',
        ];
    }

    public function precos(): HasMany
    {
        return $this->hasMany(Preco::class);
    }

    public function franquias(): HasMany
    {
        return $this->hasMany(FranquiaPlano::class);
    }

    /**
     * Servico que pode ser efetivamente consultado hoje.
     *
     * `exige_liberacao` cobre SCR e afins: eles existem no catalogo, aparecem
     * para a administracao e podem ser precificados, mas nenhuma chamada sai
     * para o fornecedor antes da homologacao juridica e contratual.
     */
    public function disponivel(): bool
    {
        return $this->ativo && ! $this->exige_liberacao;
    }

    public function scopeDisponiveis(Builder $query): Builder
    {
        return $query->where('ativo', true)->where('exige_liberacao', false);
    }

    public function scopeDaCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }

    public function rotuloCategoria(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }
}
