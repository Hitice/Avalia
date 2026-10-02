<?php

namespace App\Models;

use App\Enums\SituacaoEtiqueta;
use App\Support\CodigoCurto;
use App\Support\Dono;
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
        'vendida_em', 'vence_em', 'avisada_em', 'consignada_para_id', 'consignada_em', 'valor_cents', 'custo_cents', 'gravada_em',
        'asaas_subscription_id', 'total_acessos', 'ultimo_acesso_em', 'staff_id',
        'vendedor_id', 'dono_tipo', 'dono_id',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoEtiqueta::class,
            'sequencia' => 'integer',
            'valor_cents' => 'integer',
            'custo_cents' => 'integer',
            'total_acessos' => 'integer',
            'vendida_em' => 'datetime',
            'vence_em' => 'date',
            'avisada_em' => 'datetime',
            'consignada_em' => 'datetime',
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

    /** Quem gerou a tiragem. Trilha de producao, nao de venda. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** Quem vendeu esta plaquinha. E daqui que sai o repasse. */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'vendedor_id');
    }

    /**
     * As plaquinhas vendidas dentro de um periodo.
     *
     * `vendida_em` e a data da venda de verdade, e nao `created_at`: a placa e
     * gerada em branco semanas antes, e contar pela criacao creditaria a venda
     * ao mes em que a grafica imprimiu.
     */
    public function scopeVendidasEntre(Builder $consulta, \DateTimeInterface $de, \DateTimeInterface $ate): Builder
    {
        return $consulta->whereNotNull('vendida_em')->whereBetween('vendida_em', [$de, $ate]);
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
     * O que a conta logada enxerga da tiragem.
     *
     * A EQUIPE ve tudo: administracao e vendedor. Nao e descuido, e a decisao de
     * quem opera. A casa e pequena e o atendimento nao e de carteira fechada:
     * quem esta na mesa atende a placa que tocar o telefone, e a placa parada
     * porque o vendedor dela esta em campo custa mais que o risco de alguem
     * abrir o que nao vendeu. Cada troca de destino fica na auditoria com nome,
     * entao o controle e depois do ato, e nao antes.
     *
     * A primeira versao disto dava ao vendedor so o estoque em branco, as
     * vendas dele e o que era dele por dono. Ficou de fora a venda de outro
     * vendedor, e era esse o pedido: que apareca tudo.
     *
     * Cliente e produtor seguem so pelo dono, e essa linha nao se mexe: e o que
     * impede um cliente de trocar o destino da placa de outro. Ver
     * App\Support\Dono.
     */
    public function consignadaPara(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'consignada_para_id');
    }

    /**
     * O estoque pessoal de um vendedor: na mao dele e ainda nao vendida.
     *
     * Consulta, e nao contador: contador precisa ser decrementado por quem
     * vende, e o dia em que a venda sair por outro caminho ele fica errado sem
     * ninguem notar.
     */
    public function scopeNoEstoqueDe(Builder $consulta, int $staffId): Builder
    {
        return $consulta->where('consignada_para_id', $staffId)->whereNull('vendida_em');
    }

    /** As que ninguem pegou ainda: o bolo comum da equipe. */
    public function scopeSemDono(Builder $consulta): Builder
    {
        return $consulta->whereNull('consignada_para_id')->whereNull('vendida_em');
    }

    public function scopeVisiveis(Builder $consulta): Builder
    {
        return Dono::tipo() === 'staff' ? $consulta : Dono::limitar($consulta);
    }

    /**
     * A mesma regra de `scopeVisiveis`, para um registro na mao.
     *
     * Existe separada porque a consulta filtra e esta responde: sem ela, o
     * vendedor veria a placa na lista e levaria 404 ao abrir, que e pior que
     * nao ver.
     */
    public function podeMexer(): bool
    {
        return Dono::tipo() === 'staff' || Dono::pode($this);
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
