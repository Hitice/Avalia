<?php

namespace App\Models;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Uma venda parcelada, do pedido ate a ultima parcela.
 *
 * Guarda preco, parcelamento e taxa copiados da oferta: a oferta muda, o
 * contratado nao.
 */
class Pedido360 extends Model
{
    protected $table = 'pedidos_360';

    /** A venda so vira parcela depois de passar por estas duas portas. */
    public const EXIGE_PARA_EFETIVAR = ['contrato_assinado_em', 'entrada paga'];

    protected $fillable = [
        'chave', 'oferta_360_id', 'produtor_id',
        'cliente_nome', 'cliente_documento', 'cliente_documento_hash', 'cliente_email', 'cliente_telefone',
        'cliente_nascimento', 'cliente_endereco',
        'situacao', 'situacao_financeira',
        'valor_total_cents', 'entrada_cents', 'parcelas', 'valor_parcela_cents', 'taxa_bps',
        'melhor_dia', 'analise_versao', 'analise_motivo', 'analise_em',
        'contrato_assinado_em', 'efetivado_em', 'cancelado_em', 'cancelamento_motivo',
        'asaas_customer_id', 'asaas_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'cliente_documento' => 'encrypted',
            'cliente_telefone' => 'encrypted',
            'cliente_endereco' => 'encrypted',
            'cliente_nascimento' => 'date',
            'valor_total_cents' => 'integer',
            'entrada_cents' => 'integer',
            'parcelas' => 'integer',
            'valor_parcela_cents' => 'integer',
            'taxa_bps' => 'integer',
            'melhor_dia' => 'integer',
            'analise_em' => 'datetime',
            'contrato_assinado_em' => 'datetime',
            'efetivado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $pedido) => $pedido->chave ??= (string) Str::ulid());

        // O hash e derivado do documento e acompanha qualquer mudanca dele.
        // Deixar isso a cargo de quem grava seria esquecer em algum lugar, e o
        // esquecimento aqui e silencioso: a busca simplesmente para de achar.
        static::saving(function (self $pedido) {
            if ($pedido->isDirty('cliente_documento')) {
                $pedido->cliente_documento_hash = Documento::hash($pedido->cliente_documento);
            }
        });
    }

    /**
     * A URL publica usa a chave, e nunca o id.
     *
     * A pagina de resultado do checkout nao tem login, porque quem acabou de
     * comprar nao tem conta. Com id sequencial, trocar o numero na barra de
     * endereco mostrava a compra de outra pessoa.
     */
    public function getRouteKeyName(): string
    {
        return 'chave';
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta360::class, 'oferta_360_id');
    }

    public function produtor(): BelongsTo
    {
        return $this->belongsTo(Produtor::class);
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela360::class, 'pedido_360_id');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento360::class, 'pedido_360_id');
    }

    /** A entrada e a parcela zero: mesmo carne, mesma cobranca, outro numero. */
    public function entrada(): ?Parcela360
    {
        return $this->parcelas()->where('numero', 0)->first();
    }

    /**
     * Pode emitir o carne?
     *
     * Contrato assinado E entrada confirmada, nesta ordem de importancia.
     * Emitir boleto antes do contrato e cobrar por algo que ninguem assinou, e
     * foi por isso que a regra virou metodo em vez de checagem espalhada.
     */
    public function podeParcelar(): bool
    {
        return $this->contrato_assinado_em !== null
            && $this->entrada()?->situacao === 'paga';
    }

    /** Quanto a plataforma fica de cada real, em centavos, para um valor. */
    public function taxaSobre(int $centavos): int
    {
        return intdiv($centavos * $this->taxa_bps, 10000);
    }

    public function documentoRotulo(): string
    {
        return Documento::formatar($this->cliente_documento);
    }
}
