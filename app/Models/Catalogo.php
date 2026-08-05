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

    /** Aliquota de imposto legivel: 1350 -> "13,5%". */
    public function impostoRotulo(): string
    {
        return self::pontosRotulo($this->imposto_bps);
    }

    /**
     * Comissao do vendedor em pontos-base, para entrar no calculo de margem.
     *
     * Aliquota unica: comissionando sobre lucro, consumo a mais ja rende
     * comissao a mais sozinho, sem precisar de adicional por excedente.
     */
    public function comissaoBps(): int
    {
        return Comissao::PCT_PADRAO * 100;
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

    /**
     * Quanto do preco de venda vai para o fornecedor nesta faixa, em bps.
     *
     * Media simples entre os servicos que ja tem custo cadastrado, porque nao
     * se sabe qual mix o cliente vai consultar. E estimativa, e a tela diz isso:
     * serve para simular contrato, nao para fechar competencia.
     */
    public function custoSobreVendaBps(int $faixaCents): ?int
    {
        $razoes = $this->precos()
            ->where('consumo_minimo_cents', $faixaCents)
            ->whereNotNull('custo_cents')
            ->where('preco_cents', '>', 0)
            ->get()
            ->map(fn (Preco $preco) => $preco->custo_cents * 10_000 / $preco->preco_cents);

        return $razoes->isEmpty() ? null : (int) round($razoes->avg());
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
