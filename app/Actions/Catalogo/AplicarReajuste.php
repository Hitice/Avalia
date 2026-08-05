<?php

namespace App\Actions\Catalogo;

use App\Enums\Categoria;
use App\Models\Catalogo;
use Illuminate\Support\Facades\DB;

/**
 * Aplica um percentual sobre os precos, opcionalmente numa categoria so.
 *
 * Um UPDATE unico com o arredondamento feito no banco: trazer 301 linhas ate o
 * PHP para multiplicar cada uma seria lento sem necessidade.
 */
class AplicarReajuste
{
    public function __invoke(Catalogo $catalogo, float $percentual, ?Categoria $categoria = null): int
    {
        // %.6F garante ponto decimal e nada de notacao cientifica ao entrar na
        // SQL. O valor ja passou pela validacao, mas o formato e o que impede
        // uma surpresa de locale virar SQL invalida.
        $fator = sprintf('%.6F', 1 + ($percentual / 100));

        return $catalogo->precos()
            ->when($categoria, fn ($q) => $q->whereHas('servico', fn ($s) => $s->where('categoria', $categoria)))
            ->update([
                'preco_cents' => DB::raw('cast(round(preco_cents * '.$fator.') as integer)'),
                'updated_at' => now(),
            ]);
    }
}
