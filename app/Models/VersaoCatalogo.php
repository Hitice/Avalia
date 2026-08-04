<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Uma tabela de precos datada.
 *
 * Toda mudanca comercial nasce como rascunho, e ativada e nunca mais e tocada.
 * Contrato, consulta e fatura apontam para a versao que estava vigente quando
 * foram emitidos, entao reajustar preco hoje nao pode alterar o que ja foi
 * cobrado ontem.
 */
class VersaoCatalogo extends Model
{
    use HasFactory;

    protected $table = 'versoes_catalogo';

    public const SITUACOES = [
        'rascunho' => 'Rascunho',
        'agendada' => 'Agendada',
        'ativa' => 'Ativa',
        'encerrada' => 'Encerrada',
    ];

    protected $fillable = [
        'rotulo', 'observacao', 'situacao', 'vigencia_inicio', 'vigencia_fim',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'congelada_em' => 'datetime',
        ];
    }

    public function precos(): HasMany
    {
        return $this->hasMany(Preco::class, 'versao_id');
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class, 'versao_id');
    }

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('situacao', 'ativa');
    }

    /** A tabela que vale agora, ou null se o catalogo ainda nao foi ativado. */
    public static function vigente(): ?self
    {
        return static::ativa()->first();
    }

    /**
     * So rascunho aceita alteracao de preco. Depois de ativada a versao vira
     * documento historico — mudar exige duplicar.
     */
    public function podeEditar(): bool
    {
        return $this->situacao === 'rascunho';
    }

    public function estaCongelada(): bool
    {
        return ! $this->podeEditar();
    }

    /**
     * Faixas de consumo minimo oferecidas por esta versao, em centavos.
     *
     * Sao os proprios valores gravados nos precos: se uma versao futura criar
     * a faixa de R$ 3.000, ela aparece aqui sem migration nenhuma.
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
            ->whereHas('servico', fn (Builder $q) => $q->where('codigo', $codigo))
            ->value('preco_cents');

        return $preco === null ? null : (int) $preco;
    }

    /**
     * Poe esta versao em vigor e encerra a anterior.
     *
     * Duas versoes ativas ao mesmo tempo tornariam o preco de uma consulta
     * indeterminado, entao a troca acontece numa transacao so.
     */
    public function ativar(): void
    {
        DB::transaction(function () {
            static::ativa()
                ->whereKeyNot($this->getKey())
                ->update([
                    'situacao' => 'encerrada',
                    'vigencia_fim' => now()->toDateString(),
                    'updated_at' => now(),
                ]);

            $this->forceFill([
                'situacao' => 'ativa',
                'vigencia_inicio' => $this->vigencia_inicio ?? now()->toDateString(),
                'congelada_em' => $this->congelada_em ?? now(),
            ])->save();
        });
    }

    /**
     * Cria um rascunho com os mesmos precos, para reajuste.
     *
     * E o unico caminho para mudar valor: a versao antiga continua legivel do
     * jeito que estava quando alguem assinou o contrato.
     */
    public function duplicar(string $rotulo): self
    {
        return DB::transaction(function () use ($rotulo) {
            $nova = static::create([
                'rotulo' => $rotulo,
                'situacao' => 'rascunho',
                'observacao' => "Duplicada de: {$this->rotulo}",
            ]);

            $this->precos()
                ->get(['servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents'])
                ->each(function (Preco $preco) use ($nova) {
                    $copia = $nova->precos()->make([
                        'servico_id' => $preco->servico_id,
                        'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                        'preco_cents' => $preco->preco_cents,
                        'custo_cents' => $preco->custo_cents,
                    ]);

                    // Evita que a guarda de congelamento va ao banco buscar a
                    // versao uma vez por linha copiada.
                    $copia->setRelation('versao', $nova);
                    $copia->save();
                });

            return $nova;
        });
    }

    public function rotuloSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }
}
