<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contato extends Model
{
    protected $fillable = ['nome', 'documento', 'email', 'whatsapp', 'telefone', 'cidade', 'uf', 'origem'];

    public function vinculos(): HasMany
    {
        return $this->hasMany(Vinculo::class);
    }

    public function interacoes(): HasMany
    {
        return $this->hasMany(Interacao::class)->orderByDesc('ocorrido_em');
    }

    /** @return list<string> */
    public function papeis(): array
    {
        return $this->vinculos->pluck('papel')->unique()->values()->all();
    }
}
