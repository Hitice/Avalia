<?php

namespace App\Models;

use App\Support\Comissao;
use Illuminate\Database\Eloquent\Collection as ColecaoEloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O catalogo: a tabela de precos da Avalia.
 *
 * Editavel a qualquer momento. O que garante que um reajuste nao mude cobranca
 * ja emitida nao e travar esta tabela. Sao a consulta e cada fatura gravarem
 * preco e custo no momento da emissao (PDD.md, secoes 7 e 8). Quem cobra guarda
 * o proprio valor; aqui fica so quanto custa hoje.
 */
class Catalogo extends Model
{
    use HasFactory;

    protected $table = 'catalogos';

    protected $fillable = ['rotulo', 'observacao', 'vigencia_inicio', 'imposto_bps', 'margem_alvo_bps', 'degrau_margem_bps'];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'imposto_bps' => 'integer',
            'margem_alvo_bps' => 'integer',
            'degrau_margem_bps' => 'integer',
        ];
    }

    /** Aliquota de imposto legivel: 860 -> "8,6%". */
    public function impostoRotulo(): string
    {
        return self::pontosRotulo($this->imposto_bps);
    }

    /** Margem liquida alvo legivel: 3000 -> "30%". */
    public function margemAlvoRotulo(): string
    {
        return self::pontosRotulo($this->margem_alvo_bps);
    }

    /**
     * Comissao do vendedor em pontos-base, para entrar no calculo de margem.
     *
     * Usa a aliquota normal. No mes com excedente ela sobe para 20% e a margem
     * cai na mesma medida; precificar pelo pior mes encareceria todo o catalogo
     * por causa da excecao.
     */
    public function comissaoBps(): int
    {
        return Comissao::PCT_PADRAO * 100;
    }

    /**
     * Margem alvo de cada faixa, em pontos-base.
     *
     * A margem alvo e o piso e vale para a MAIOR faixa. Cada degrau abaixo
     * soma degrau_margem_bps, entao o preco unitario cai conforme o cliente
     * sobe de pacote e o pacote maior fica de fato mais vantajoso.
     *
     * @param  list<int>  $faixas  em ordem crescente
     * @return array<int, int> faixa em centavos => margem em bps
     */
    public function margemPorFaixa(array $faixas): array
    {
        $degraus = max(0, count($faixas) - 1);
        $margens = [];

        foreach (array_values($faixas) as $posicao => $faixa) {
            $margens[$faixa] = $this->margem_alvo_bps + $this->degrau_margem_bps * ($degraus - $posicao);
        }

        return $margens;
    }

    public function degrauRotulo(): string
    {
        return self::pontosRotulo($this->degrau_margem_bps);
    }

    private static function pontosRotulo(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2, ',', '.'), '0'), ',').'%';
    }

    public function precos(): HasMany
    {
        return $this->hasMany(Preco::class, 'catalogo_id');
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class, 'catalogo_id');
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

    /**
     * Faixas ordenadas a partir de precos ja carregados, sem ir ao banco.
     *
     * O cast para int e obrigatorio: driver de banco decide se bigint volta
     * como int ou como string, e faixa em string faz `$faixa === 0` falhar,
     * trocando "Sem minimo" por "R$ 0,00" no cabecalho.
     *
     * @return list<int>
     */
    public static function faixasDe(ColecaoEloquent $precos): array
    {
        return $precos
            ->pluck('consumo_minimo_cents')
            ->map(fn ($faixa) => (int) $faixa)
            ->unique()
            ->sort()
            ->values()
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
