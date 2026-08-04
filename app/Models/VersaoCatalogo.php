<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O catalogo: a tabela de precos da Avalia.
 *
 * Editavel a qualquer momento. O que garante que um reajuste nao mude cobranca
 * ja emitida nao e travar esta tabela — e cada consulta e cada fatura gravarem
 * preco e custo no momento da emissao (PDD.md, secoes 7 e 8). Quem cobra guarda
 * o proprio valor; aqui fica so quanto custa hoje.
 */
class VersaoCatalogo extends Model
{
    use HasFactory;

    protected $table = 'versoes_catalogo';

    protected $fillable = ['rotulo', 'observacao', 'vigencia_inicio', 'imposto_bps'];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'imposto_bps' => 'integer',
        ];
    }

    /** Aliquota de imposto legivel: 2700 -> "27%". */
    public function impostoRotulo(): string
    {
        return rtrim(rtrim(number_format($this->imposto_bps / 100, 2, ',', '.'), '0'), ',').'%';
    }

    public function precos(): HasMany
    {
        return $this->hasMany(Preco::class, 'versao_id');
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class, 'versao_id');
    }

    /** O catalogo em uso. Havendo mais de um, o mais recente. */
    public static function vigente(): ?self
    {
        return static::orderByDesc('id')->first();
    }

    /**
     * Faixas de consumo minimo oferecidas, em centavos.
     *
     * Sao os proprios valores gravados nos precos: criar a faixa de R$ 3.000 e
     * so cadastrar preco nela, sem migration nenhuma.
     *
     * @return list<int>
     */
    public function faixas(): array
    {
        return $this->precos()
            ->distinct()
            ->orderBy('consumo_minimo_cents')
            ->pluck('consumo_minimo_cents')
            ->map(fn ($faixa) => (int) $faixa)
            ->all();
    }

    /** Preco de venda de um servico numa faixa, ou null se nao ha. */
    public function precoDe(string $codigo, int $faixaCents): ?int
    {
        $preco = $this->precos()
            ->where('consumo_minimo_cents', $faixaCents)
            ->whereHas('servico', fn ($q) => $q->where('codigo', $codigo))
            ->value('preco_cents');

        return $preco === null ? null : (int) $preco;
    }
}
