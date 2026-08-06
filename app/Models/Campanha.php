<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campanha extends Model
{
    protected $fillable = ['nome', 'oferta', 'inicio', 'fim', 'ativa'];

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date', 'ativa' => 'boolean'];
    }

    /** Ativa e dentro do periodo. Sem fim, vale ate ser desligada. */
    public function scopeVigente($consulta)
    {
        return $consulta->where('ativa', true)
            ->whereDate('inicio', '<=', today())
            ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>=', today()));
    }

    /**
     * Se o texto pode aparecer na pagina publica.
     *
     * A vitrine tem uma regra dura: preco e fornecedor moram atras do login
     * (PDD, secao 7). A campanha e texto livre da administracao, entao a regra
     * precisa valer em execucao, e nao so no teste da pagina: texto que vaza
     * preco ou fornecedor devolve a vitrine ao texto fixo em vez de publicar.
     */
    public function seguraParaVitrine(): bool
    {
        $texto = $this->nome.' '.$this->oferta;

        if (str_contains($texto, 'R$')) {
            return false;
        }

        return preg_match('/\b(boa vista|equifax|spc|serasa|custo|margem|lucro)\b/iu', $texto) !== 1;
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class);
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class);
    }
}
