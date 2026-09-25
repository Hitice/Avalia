<?php

namespace App\Models;

use App\Enums\SituacaoEtiqueta;
use App\Support\CodigoCurto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Uma plaquinha de QR e NFC, ou um codigo dinamico avulso.
 *
 * O codigo impresso e um endereco permanente da Avalia, e nao o link do
 * cliente: o que muda quando o cliente troca de site e o `destino`, nunca o
 * `codigo`. E isso que faz a placa no balcao continuar valendo.
 *
 * Vencimento nao e coluna de situacao. `situacao` guarda o que alguem decidiu
 * (em branco, ativa, suspensa, baixada) e a data decide o resto, em
 * `estado()`. Ver App\Enums\SituacaoEtiqueta para o porque.
 */
class Etiqueta extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo', 'lote_id', 'sequencia', 'tipo', 'situacao', 'destino',
        'titulo', 'cliente_nome', 'cliente_contato',
        'vendida_em', 'vence_em', 'avisada_em', 'valor_cents', 'gravada_em',
        'asaas_subscription_id', 'total_acessos', 'ultimo_acesso_em', 'staff_id',
        'dono_tipo', 'dono_id',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoEtiqueta::class,
            'sequencia' => 'integer',
            'valor_cents' => 'integer',
            'total_acessos' => 'integer',
            'vendida_em' => 'datetime',
            'vence_em' => 'date',
            'avisada_em' => 'datetime',
            'gravada_em' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    /**
     * Some do cache assim que alguem mexe na plaquinha.
     *
     * Evento do model, e nao chamada dentro de cada Action: o dia em que
     * alguem escrever a quinta forma de suspender uma etiqueta, ela vai
     * continuar limpando o cache sem precisar lembrar disso. Esquecimento aqui
     * e silencioso, e apareceria como "troquei o destino e nao mudou nada".
     */
    protected static function booted(): void
    {
        $esquecer = fn (self $etiqueta) => Cache::forget(self::chaveDeCache($etiqueta->codigo));

        static::saved($esquecer);
        static::deleted($esquecer);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteEtiqueta::class, 'lote_id');
    }

    public function destinos(): HasMany
    {
        return $this->hasMany(DestinoEtiqueta::class);
    }

    public function renovacoes(): HasMany
    {
        return $this->hasMany(RenovacaoEtiqueta::class);
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(AcessoEtiqueta::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /*
    |--------------------------------------------------------------------------
    | A busca da leitura
    |--------------------------------------------------------------------------
    */

    public static function chaveDeCache(string $codigo): string
    {
        return 'etiqueta:'.$codigo;
    }

    /**
     * A etiqueta de um codigo ja normalizado, passando pelo cache.
     *
     * Esta e a consulta mais quente do sistema: roda uma vez por encostada de
     * celular em qualquer plaquinha em campo. O cache curto tira o MySQL do
     * caminho sem deixar uma troca de destino demorar a valer, e o evento de
     * `saved` limpa a chave antes disso quando a troca acontece de verdade.
     *
     * Codigo inexistente tambem entra no cache, como `false`. Sem isso, quem
     * varrer codigos aleatorios bate no banco em cada tentativa.
     */
    public static function porCodigo(string $codigo): ?self
    {
        $achada = Cache::remember(
            self::chaveDeCache($codigo),
            (int) config('etiquetas.cache_segundos'),
            fn () => self::firstWhere('codigo', $codigo) ?? false,
        );

        return $achada === false ? null : $achada;
    }

    /*
    |--------------------------------------------------------------------------
    | O estado de verdade
    |--------------------------------------------------------------------------
    */

    /**
     * O estado que a leitura da plaquinha enxerga.
     *
     * Soma o que alguem decidiu com o que a data diz. Devolve string, e nao o
     * enum, porque dois dos valores possiveis (`carencia` e `vencida`) nao
     * existem no banco: eles sao conclusao, nao registro.
     *
     * @return 'em_branco'|'ativa'|'carencia'|'vencida'|'suspensa'|'baixada'
     */
    public function estado(): string
    {
        if ($this->situacao !== SituacaoEtiqueta::Ativa) {
            return $this->situacao->value;
        }

        // Placa vendida sem prazo (cortesia, demonstracao, brinde) nao vence.
        if ($this->vence_em === null) {
            return 'ativa';
        }

        // `endOfDay` porque `vence_em` e data, e quem vence hoje tem o dia de
        // hoje inteiro. Comparar contra a meia-noite mataria a placa de manha.
        if ($this->vence_em->endOfDay()->isFuture()) {
            return 'ativa';
        }

        return $this->fimDaCarencia()->isFuture() ? 'carencia' : 'vencida';
    }

    /** Ate quando a plaquinha vencida ainda redireciona. */
    public function fimDaCarencia(): \Illuminate\Support\Carbon
    {
        return $this->vence_em->copy()->endOfDay()->addDays((int) config('etiquetas.carencia_dias'));
    }

    /**
     * Se a leitura deve virar redirecionamento.
     *
     * Carencia redireciona igual a ativa, de proposito: e a diferenca entre
     * avisar o dono e derrubar a loja dele.
     */
    public function redireciona(): bool
    {
        return in_array($this->estado(), ['ativa', 'carencia'], true) && filled($this->destino);
    }

    public function emCarencia(): bool
    {
        return $this->estado() === 'carencia';
    }

    public function vencida(): bool
    {
        return $this->estado() === 'vencida';
    }

    /*
    |--------------------------------------------------------------------------
    | Enderecos
    |--------------------------------------------------------------------------
    */

    /** O endereco publico impresso na plaquinha. */
    public function url(): string
    {
        return CodigoCurto::url($this->codigo);
    }

    /** O mesmo endereco, em maiusculo, do jeito que entra no QR. */
    public function urlParaQr(): string
    {
        return CodigoCurto::urlParaQr($this->codigo);
    }

    /** Como o arquivo sai nomeado dentro do ZIP do lote. */
    public function nomeDeArquivo(): string
    {
        return $this->sequencia === null
            ? $this->codigo
            : str_pad((string) $this->sequencia, 4, '0', STR_PAD_LEFT).'-'.$this->codigo;
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas
    |--------------------------------------------------------------------------
    */

    public function scopeAtivas(Builder $consulta): Builder
    {
        return $consulta->where('situacao', SituacaoEtiqueta::Ativa);
    }

    /**
     * As que vencem no dia combinado e ainda nao foram avisadas.
     *
     * `avisada_em` nulo e o que torna o comando diario idempotente: cron que
     * roda duas vezes nao manda o aviso duas vezes.
     */
    public function scopeAvisarEm(Builder $consulta, \DateTimeInterface $dia): Builder
    {
        return $consulta->ativas()
            ->whereDate('vence_em', $dia)
            ->whereNull('avisada_em');
    }
}
