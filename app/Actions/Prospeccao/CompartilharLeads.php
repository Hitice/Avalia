<?php

namespace App\Actions\Prospeccao;

use App\Models\Lead;
use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Entrega um recorte da base de leads a um vendedor.
 *
 * Distribuir lead e decisao da administracao (PDD.md, secao dos pedidos de
 * contato): o vendedor nunca alcanca a base inteira, so o que foi passado para
 * ele. Por isso a acao grava quem decidiu e quando, e nao apenas o vinculo.
 *
 * Compartilhar de novo o que ja esta com a pessoa nao reescreve a data do
 * primeiro compartilhamento: "esta com ele desde quando" e a pergunta que a
 * administracao faz quando o lead nao andou.
 *
 * So vendedor recebe. Administrador ja ve a base toda, e um vinculo com ele
 * criaria uma segunda resposta para "com quem esta este lead".
 */
class CompartilharLeads
{
    /**
     * @param  list<int>  $leads
     * @return int quantos vinculos novos foram criados
     */
    public function __invoke(array $leads, Staff $vendedor): int
    {
        if ($vendedor->papel !== 'vendedor') {
            return 0;
        }

        return DB::transaction(function () use ($leads, $vendedor) {
            // Lead removido nao entra: ele saiu do trabalho de proposito, e
            // reaparecer na tela de um vendedor desfaria a decisao em silencio.
            $alvos = Lead::whereIn('id', $leads)->pluck('id');

            $jaTem = $vendedor->leads()->whereIn('leads.id', $alvos)->pluck('leads.id');
            $novos = $alvos->diff($jaTem);

            if ($novos->isEmpty()) {
                return 0;
            }

            $agora = now();
            $autor = auth('staff')->id();

            $vendedor->leads()->attach(
                $novos->mapWithKeys(fn (int $id) => [$id => [
                    'compartilhado_em' => $agora,
                    'compartilhado_por' => $autor,
                ]])->all()
            );

            Auditar::registrar('leads.compartilhados', $vendedor, [
                'leads' => $novos->count(),
                'vendedor' => $vendedor->nome,
            ]);

            return $novos->count();
        });
    }
}
