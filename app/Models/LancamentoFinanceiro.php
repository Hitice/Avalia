<?php

namespace App\Models;

use App\Enums\NaturezaLancamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Um evento financeiro. Os valores ficam nas partidas, que somam zero. */
class LancamentoFinanceiro extends Model
{
    protected $table = 'lancamentos_financeiros';

    public const UPDATED_AT = null;

    protected $fillable = [
        'natureza', 'descricao', 'competencia', 'ocorrido_em', 'contraparte',
        'documento', 'comprovante', 'origem_tipo', 'origem_id', 'staff_id', 'estorna_id',
    ];

    protected function casts(): array
    {
        return [
            'natureza' => NaturezaLancamento::class,
            'ocorrido_em' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaFinanceira::class, 'lancamento_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function estorna(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorna_id');
    }

    public function estornos(): HasMany
    {
        return $this->hasMany(self::class, 'estorna_id');
    }

    /** Ja foi desfeito? Estorno de estorno nao existe. */
    public function estornado(): bool
    {
        return $this->estornos()->exists();
    }

    /**
     * Da para apagar, ou so estornar?
     *
     * A mesma linha que App\Actions\Socios\ExcluirLancamento confere, aqui para
     * a tela decidir qual botao mostrar. Oferecer um botao que sempre recusa
     * ensina o operador a nao clicar em botao nenhum.
     */
    public function podeSerApagado(): bool
    {
        return $this->origem_tipo === null
            && $this->estorna_id === null
            && $this->competencia === now()->format('Y-m')
            && ! $this->estornado();
    }

    /** O valor que representa o lancamento: a soma do que DEBITOU. */
    public function valorCents(): int
    {
        return (int) $this->partidas->where('valor_cents', '>', 0)->sum('valor_cents');
    }

    public function scopeDaCompetencia(Builder $consulta, string $competencia): Builder
    {
        return $consulta->where('competencia', $competencia);
    }
}
