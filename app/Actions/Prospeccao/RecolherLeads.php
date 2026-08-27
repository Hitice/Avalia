<?php

namespace App\Actions\Prospeccao;

use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Tira da mao do vendedor os leads que ele nao vai mais trabalhar.
 *
 * O lead continua na base: o que termina e o acesso daquela pessoa a ele. Sem
 * isto, distribuicao errada so se corrigia no banco de dados, e um vendedor que
 * saiu da equipe continuaria com a lista de contatos aberta na tela.
 */
class RecolherLeads
{
    /**
     * @param  list<int>  $leads
     * @return int quantos vinculos foram desfeitos
     */
    public function __invoke(array $leads, Staff $vendedor): int
    {
        return DB::transaction(function () use ($leads, $vendedor) {
            $desfeitos = $vendedor->leads()->detach($leads);

            if ($desfeitos > 0) {
                Auditar::registrar('leads.recolhidos', $vendedor, [
                    'leads' => $desfeitos,
                    'vendedor' => $vendedor->nome,
                ]);
            }

            return $desfeitos;
        });
    }
}
