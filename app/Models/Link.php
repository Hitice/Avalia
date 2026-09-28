<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Um endereco longo, guardado atras de um codigo curto.
 *
 * Existe por causa da tag NFC: as que a casa usa tem cerca de 140 bytes uteis,
 * e endereco de campanha com parametros de origem nao cabe. O que cabe e
 * `avaliaone.com.br/l/K7M2PX`.
 */
class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo', 'apelido', 'destino', 'titulo', 'ativo', 'cliques', 'ultimo_clique_em', 'staff_id',
        'dono_tipo', 'dono_id',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'cliques' => 'integer',
            'ultimo_clique_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Mesma razao da etiqueta: evento do model, e nao chamada dentro de
        // cada Action, para nenhuma forma futura de editar esquecer de limpar.
        $esquecer = function (self $link) {
            Cache::forget(self::chaveDeCache($link->codigo));

            // O apelido tem chave propria, e o ANTIGO tambem precisa sair: sem
            // isso, trocar o apelido deixaria o velho respondendo do cache por
            // mais um minuto, apontando para onde o link nao aponta mais.
            foreach (array_filter([$link->apelido, $link->getOriginal('apelido')]) as $apelido) {
                Cache::forget(self::chaveDeCache($apelido));
            }
        };

        static::saved($esquecer);
        static::deleted($esquecer);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public static function chaveDeCache(string $codigo): string
    {
        return 'link:'.$codigo;
    }

    /** O link de um codigo ja normalizado, passando pelo cache. */
    public static function porCodigo(string $codigo): ?self
    {
        return self::doCache($codigo, fn () => self::firstWhere('codigo', $codigo));
    }

    /**
     * O link de um apelido, sem diferenciar maiuscula de minuscula.
     *
     * `LOWER()` em vez de comparacao direta porque a colacao decide isso no
     * MySQL e nao decide no SQLite dos testes: sem normalizar, o apelido
     * digitado em caixa diferente abriria em producao e falharia na suite, ou
     * o contrario, que e pior.
     */
    public static function porApelido(string $apelido): ?self
    {
        return self::doCache(
            $apelido,
            fn () => self::whereRaw('LOWER(apelido) = ?', [mb_strtolower($apelido)])->first(),
        );
    }

    /**
     * Ida ao banco com cache curto, inclusive do que nao existe.
     *
     * Sem guardar a ausencia, quem varresse enderecos aleatorios bateria no
     * banco em cada tentativa.
     */
    private static function doCache(string $chave, \Closure $buscar): ?self
    {
        $achado = Cache::remember(
            self::chaveDeCache($chave),
            (int) config('etiquetas.cache_segundos'),
            fn () => $buscar() ?? false,
        );

        return $achado === false ? null : $achado;
    }

    /**
     * O endereco curto, que e o que vai para a tag.
     *
     * O apelido ganha do codigo quando existe, porque e ele que a pessoa
     * escolheu mostrar. O codigo continua valendo sempre, em paralelo: link ja
     * gravado numa tag nao pode parar de abrir so porque ganhou nome bonito.
     */
    public function url(): string
    {
        return $this->apelido
            ? url('/'.$this->apelido)
            : route('l', ['codigo' => $this->codigo]);
    }

    /** O endereco pelo codigo sorteado, que nunca muda. */
    public function urlDoCodigo(): string
    {
        return route('l', ['codigo' => $this->codigo]);
    }

    /**
     * Quantos bytes o endereco curto ocupa.
     *
     * E o numero que decide se o link cabe na tag, e por isso ele aparece na
     * tela ao lado do limite. ASCII puro, entao byte e caractere.
     */
    public function bytes(): int
    {
        return strlen($this->url());
    }

    public function economia(): int
    {
        return max(0, strlen($this->destino) - $this->bytes());
    }
}
