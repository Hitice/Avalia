<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A competencia fechada de uma empresa.
 *
 * Guarda a cascata inteira, e nao so o total: mensalidade, consumo, imposto,
 * custo, lucro e comissao, mais a aliquota e o percentual usados. Uma fatura de
 * seis meses atras precisa continuar explicavel sem consultar o catalogo de
 * hoje, e o repasse ao vendedor tem de poder ser reconferido linha a linha.
 *
 * Vencimento e dia 10, data fixa de calendario igual para todos, e o bloqueio
 * das consultas vem dez dias depois (PDD.md, secao 5).
 */
class Fatura extends Model
{
    use HasFactory;

    protected $table = 'faturas';

    /** Dia do vencimento e dias de tolerancia ate o bloqueio das consultas. */
    public const DIA_VENCIMENTO = 10;

    public const DIAS_ATE_BLOQUEIO = 10;

    protected $fillable = [
        'cliente_id', 'vendedor_id', 'competencia',
        'mensalidade_cents', 'consumo_minimo_cents', 'consumo_realizado_cents',
        'consumo_faturado_cents', 'total_cents',
        'imposto_bps', 'imposto_cents', 'custo_cents', 'lucro_cents',
        'comissao_pct', 'comissao_cents', 'fechada_em',
    ];

    protected function casts(): array
    {
        return [
            'mensalidade_cents' => 'integer',
            'consumo_minimo_cents' => 'integer',
            'consumo_realizado_cents' => 'integer',
            'consumo_faturado_cents' => 'integer',
            'total_cents' => 'integer',
            'imposto_bps' => 'integer',
            'imposto_cents' => 'integer',
            'custo_cents' => 'integer',
            'lucro_cents' => 'integer',
            'comissao_pct' => 'integer',
            'comissao_cents' => 'integer',
            'fechada_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'vendedor_id');
    }

    /** Vence sempre no dia 10 do mes seguinte ao consumo. */
    public function vencimento(): \DateTimeInterface
    {
        return \Carbon\CarbonImmutable::createFromFormat('Y-m', $this->competencia)
            ->addMonth()
            ->setDay(self::DIA_VENCIMENTO)
            ->startOfDay();
    }

    /** Quanto o cliente pagou de consumo que nao usou. */
    public function pagouSemUsarCents(): int
    {
        return max(0, $this->consumo_faturado_cents - $this->consumo_realizado_cents);
    }

    public function competenciaRotulo(): string
    {
        [$ano, $mes] = explode('-', $this->competencia);

        return $mes.'/'.$ano;
    }

    public function totalRotulo(): string
    {
        return Dinheiro::brl($this->total_cents);
    }
}
