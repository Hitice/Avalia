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

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class);
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class);
    }
}
