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
        'codigo', 'destino', 'titulo', 'ativo', 'cliques', 'ultimo_clique_em', 'staff_id',
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
        $esquecer = fn (self $link) => Cache::forget(self::chaveDeCache($link->codigo));

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
        $achado = Cache::remember(
            self::chaveDeCache($codigo),
            (int) config('etiquetas.cache_segundos'),
            fn () => self::firstWhere('codigo', $codigo) ?? false,
        );

        return $achado === false ? null : $achado;
    }

    /** O endereco curto, que e o que vai para a tag. */
    public function url(): string
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
