<?php

namespace App\Models;

use App\Enums\Categoria;
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

    protected $fillable = ['codigo', 'nome', 'categoria', 'descricao', 'codigo_fornecedor', 'ativo', 'exige_liberacao'];

    /**
     * O numero de atendimento nasce sozinho, sequencial, e nunca se reusa:
     * o max() inclui servico desativado, entao numero de servico morto nao
     * renasce apontando para outro produto.
     */
    protected static function booted(): void
    {
        static::creating(function (self $servico) {
            $servico->numero ??= ((int) static::query()->max('numero')) + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'categoria' => Categoria::class,
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

    /** Numero comercial deste servico nao vai para tela nenhuma ainda. */
    public function suprimido(): bool
    {
        return $this->categoria->suprimida();
    }

    public function rotuloCategoria(): string
    {
        return $this->categoria->rotulo();
    }
}
