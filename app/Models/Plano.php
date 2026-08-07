<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O que o cliente contrata.
 *
 * Tres decisoes comerciais num registro so:
 *   catalogo_id            -> a tabela de precos que o plano le
 *   mensalidade_cents    -> cobrada sempre, consumindo ou nao
 *   consumo_minimo_cents -> piso do consumo do mes E a coluna de precos
 *
 * A fatura e mensalidade + max(minimo, consumido). Ver PDD.md, secao 6.
 * A comissao do vendedor nao sai daqui: e aliquota unica sobre o consumo
 * realizado, em App\Support\Comissao.
 */
class Plano extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'catalogo_id', 'nome', 'descricao', 'mensalidade_cents', 'consumo_minimo_cents',
        'faixa_preco_cents', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'catalogo_id' => 'integer',
            'mensalidade_cents' => 'integer',
            'consumo_minimo_cents' => 'integer',
            'faixa_preco_cents' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    public function franquias(): HasMany
    {
        return $this->hasMany(FranquiaPlano::class);
    }

    /** Menor valor que o cliente paga no mes, mesmo sem consumir nada. */
    public function faturaMinimaCents(): int
    {
        return $this->mensalidade_cents + $this->consumo_minimo_cents;
    }

    /**
     * A coluna de precos que este plano le.
     *
     * Por padrao e a faixa do proprio consumo minimo. Negociada uma faixa
     * separada (minimo de 500 com tabela de 1.000), e ela que vale para toda
     * consulta, e o minimo vira so o piso de cobranca, com valor livre.
     */
    public function faixaDePrecoCents(): int
    {
        return $this->faixa_preco_cents ?? $this->consumo_minimo_cents;
    }

    /**
     * A faixa de PRECO tem que existir no catalogo.
     *
     * Um plano lendo a coluna de R$ 300 numa versao que so tem 75/200/500 nao
     * acha preco: toda consulta sairia sem valor e a fatura fecharia errada.
     * O consumo minimo em si e livre, porque e piso de cobranca, nao coluna.
     */
    public function faixaValida(): bool
    {
        return in_array($this->faixaDePrecoCents(), $this->catalogo->faixas(), true);
    }

    public function podeVender(): bool
    {
        return $this->ativo && $this->faixaValida();
    }

    /** Preco de um servico na faixa deste plano, ou null se nao ha. */
    public function precoDe(string $codigo): ?int
    {
        return $this->catalogo->precoDe($codigo, $this->faixaDePrecoCents());
    }

    /** Consultas incluidas na mensalidade para um servico. Sem franquia = 0. */
    public function franquiaDe(string $codigo): int
    {
        return (int) $this->franquias()
            ->whereHas('servico', fn ($q) => $q->where('codigo', $codigo))
            ->value('quantidade');
    }

    /**
     * Servicos que este plano realmente consegue vender: precificados na sua
     * faixa e com integracao liberada.
     *
     * @return Collection<int, Servico>
     */
    public function servicosDisponiveis(): Collection
    {
        return Servico::query()
            ->disponiveis()
            ->whereHas('precos', fn ($q) => $q
                ->where('catalogo_id', $this->catalogo_id)
                ->where('consumo_minimo_cents', $this->faixaDePrecoCents()))
            ->orderBy('nome')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Leitura para tela
    |--------------------------------------------------------------------------
    |
    | Centavos entram no banco, reais saem na tela. A formatacao vive em
    | Dinheiro; aqui so se diz qual campo cada rotulo le.
    |
    */

    public function getMensalidadeAttribute(): string
    {
        return Dinheiro::brl($this->mensalidade_cents);
    }

    public function getConsumoMinimoAttribute(): string
    {
        return Dinheiro::faixa($this->consumo_minimo_cents);
    }

    public function getFaturaMinimaAttribute(): string
    {
        return Dinheiro::brl($this->faturaMinimaCents());
    }
}
