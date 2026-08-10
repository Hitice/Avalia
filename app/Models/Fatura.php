<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public const PAGAMENTO_PENDENTE = 'pendente';

    public const PAGAMENTO_VENCIDO = 'vencido';

    public const PAGAMENTO_LIQUIDADO = 'liquidado';

    public const PAGAMENTO_CANCELADO = 'cancelado';

    public const PAGAMENTO_ESTORNADO = 'estornado';

    /** Todas as situacoes, para filtro de tela e validacao. */
    public const SITUACOES_PAGAMENTO = [
        self::PAGAMENTO_PENDENTE,
        self::PAGAMENTO_VENCIDO,
        self::PAGAMENTO_LIQUIDADO,
        self::PAGAMENTO_CANCELADO,
        self::PAGAMENTO_ESTORNADO,
    ];

    protected $table = 'faturas';

    /** Dia do vencimento e dias de tolerancia ate o bloqueio das consultas. */
    public const DIA_VENCIMENTO = 10;

    public const DIAS_ATE_BLOQUEIO = 10;

    protected $fillable = [
        'cliente_id', 'vendedor_id', 'competencia',
        'mensalidade_cents', 'consumo_minimo_cents', 'consumo_realizado_cents',
        'consumo_faturado_cents', 'total_cents',
        'imposto_bps', 'imposto_cents', 'custo_cents', 'lucro_cents',
        'comissao_pct', 'comissao_cents', 'fechada_em', 'situacao_pagamento',
        'liquidada_em', 'comissao_liberada_em', 'estornada_em', 'consumo_bruto_cents',
        'franquia_cents', 'consumo_excedente_cents', 'aviso_vencimento_em',
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
            'liquidada_em' => 'datetime',
            'comissao_liberada_em' => 'datetime',
            'estornada_em' => 'datetime',
            'aviso_vencimento_em' => 'datetime',
            'consumo_bruto_cents' => 'integer',
            'franquia_cents' => 'integer',
            'consumo_excedente_cents' => 'integer',
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

    public function itens(): HasMany
    {
        return $this->hasMany(ItemFatura::class);
    }

    public function cobrancaAsaas(): HasOne
    {
        return $this->hasOne(CobrancaAsaas::class);
    }

    /**
     * Se a cobranca no provedor existe e tem pagina de pagamento.
     *
     * Nem sempre existe: a emissao pode ter falhado no fechamento, e a fatura
     * interna continua valida enquanto alguem reemite pela tela.
     */
    public function cobrancaEmitida(): bool
    {
        return (bool) $this->cobrancaAsaas?->invoice_url;
    }

    /**
     * Para onde levar quem abre a fatura pelo e-mail.
     *
     * Em ordem: o boleto em PDF, que e o que a pessoa espera ver e o que ela
     * anexa ao pagamento; a pagina do provedor, que abre boleto, Pix e cartao;
     * e por ultimo o portal. Nenhum dos dois primeiros pede login, e cliente
     * que nao lembra a senha nao pode travar justamente na hora de pagar.
     *
     * O portal fica por ultimo, e nao de fora: melhor uma tela que explica a
     * situacao do que um botao que nao abre nada.
     */
    public function linkDePagamento(): string
    {
        $cobranca = $this->cobrancaAsaas;

        return $cobranca?->bank_slip_url
            ?: ($cobranca?->invoice_url ?: route('empresa.faturas'));
    }

    public function estaLiquidada(): bool
    {
        return $this->situacao_pagamento === self::PAGAMENTO_LIQUIDADO;
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
        return max(0, $this->consumo_faturado_cents - $this->consumo_excedente_cents);
    }

    public function competenciaRotulo(): string
    {
        return self::competenciaRotuloDe($this->competencia);
    }

    /** O mesmo rotulo sem precisar de uma fatura em maos, para listas de filtro. */
    public static function competenciaRotuloDe(string $competencia): string
    {
        [$ano, $mes] = explode('-', $competencia);

        return $mes.'/'.$ano;
    }

    public function totalRotulo(): string
    {
        return Dinheiro::brl($this->total_cents);
    }
}
