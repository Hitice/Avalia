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
 *   versao_id            -> a tabela de precos congelada no fechamento
 *   mensalidade_cents    -> cobrada sempre, consumindo ou nao
 *   consumo_minimo_cents -> piso do consumo do mes E a coluna de precos
 *
 * A fatura e mensalidade + max(minimo, consumido). Ver PDD.md, secao 6.
 */
class Plano extends Model
{
    use HasFactory, SoftDeletes;

    /** Faixa que separa as duas aliquotas de comissao: R$ 900,00. */
    public const FAIXA_COMISSAO_CENTS = 90_000;

    public const COMISSAO_ATE_FAIXA = 20;

    public const COMISSAO_ACIMA_FAIXA = 15;

    protected $fillable = [
        'versao_id', 'nome', 'descricao', 'mensalidade_cents', 'consumo_minimo_cents', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'versao_id' => 'integer',
            'mensalidade_cents' => 'integer',
            'consumo_minimo_cents' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function versao(): BelongsTo
    {
        return $this->belongsTo(VersaoCatalogo::class, 'versao_id');
    }

    public function franquias(): HasMany
    {
        return $this->hasMany(FranquiaPlano::class);
    }

    /**
     * Aliquota de comissao do vendedor neste plano.
     *
     * Le o consumo minimo, NAO o valor da fatura. Essa distincao e a razao de
     * mensalidade e minimo serem colunas separadas: somados, o plano de R$ 900
     * passaria de R$ 900 e cairia de 20% para 15% sozinho.
     */
    public function pctComissao(): int
    {
        return $this->consumo_minimo_cents > self::FAIXA_COMISSAO_CENTS
            ? self::COMISSAO_ACIMA_FAIXA
            : self::COMISSAO_ATE_FAIXA;
    }

    /** Menor valor que o cliente paga no mes, mesmo sem consumir nada. */
    public function faturaMinimaCents(): int
    {
        return $this->mensalidade_cents + $this->consumo_minimo_cents;
    }

    /**
     * O consumo minimo contratado tem que ser uma das faixas da versao.
     *
     * Um plano de R$ 300 numa versao que so tem 75/200/500 nao acha coluna de
     * preco: toda consulta sairia sem valor e a fatura fecharia errada.
     */
    public function faixaValida(): bool
    {
        return in_array($this->consumo_minimo_cents, $this->versao->faixas(), true);
    }

    public function podeVender(): bool
    {
        return $this->ativo && $this->faixaValida();
    }

    /** Preco de um servico na faixa deste plano, ou null se nao ha. */
    public function precoDe(string $codigo): ?int
    {
        return $this->versao->precoDe($codigo, $this->consumo_minimo_cents);
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
                ->where('versao_id', $this->versao_id)
                ->where('consumo_minimo_cents', $this->consumo_minimo_cents))
            ->orderBy('nome')
            ->get();
    }

    public function getMensalidadeAttribute(): string
    {
        return Dinheiro::brl($this->mensalidade_cents);
    }

    public function getConsumoMinimoAttribute(): string
    {
        return $this->consumo_minimo_cents === 0
            ? 'Sem mínimo'
            : Dinheiro::brl($this->consumo_minimo_cents);
    }

    public function getFaturaMinimaAttribute(): string
    {
        return Dinheiro::brl($this->faturaMinimaCents());
    }
}
