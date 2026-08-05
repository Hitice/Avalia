<?php

namespace App\Actions\Catalogo;

use App\Models\Plano;

/**
 * Grava a quantidade de consultas inclusa por servico.
 *
 * Quantidade zero apaga a linha em vez de gravar zero: ausencia e zero
 * significam a mesma coisa no faturamento, nenhuma consulta gratis, e manter as
 * duas representacoes so criaria duvida na apuracao.
 *
 * So aceita servico realmente disponivel no plano, para que id chutado no
 * formulario nao vire franquia.
 */
class GravarFranquias
{
    /** @param array<int|string, int|null> $quantidades id do servico => quantidade */
    public function __invoke(Plano $plano, array $quantidades): int
    {
        $gravadas = 0;

        foreach ($plano->servicosDisponiveis()->pluck('id') as $servicoId) {
            $quantidade = (int) ($quantidades[$servicoId] ?? 0);

            if ($quantidade === 0) {
                $plano->franquias()->where('servico_id', $servicoId)->delete();

                continue;
            }

            $plano->franquias()->updateOrCreate(
                ['servico_id' => $servicoId],
                ['quantidade' => $quantidade],
            );

            $gravadas++;
        }

        return $gravadas;
    }
}
