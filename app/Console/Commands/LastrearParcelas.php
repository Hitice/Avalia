<?php

namespace App\Console\Commands;

use App\Actions\Cobranca\ParcelaNoRazao;
use App\Models\Lancamento360;
use Illuminate\Console\Command;

/** Poe no razao da casa a taxa da plataforma das parcelas do Gestor ja pagas. Repetir nao duplica. */
class LastrearParcelas extends Command
{
    protected $signature = 'avalia:lastrear-parcelas {--simular : mostra a contagem, sem gravar}';

    protected $description = 'Lanca no razao a parte da casa das parcelas do Gestor que ja foram pagas';

    public function handle(ParcelaNoRazao $contabil): int
    {
        $linhas = Lancamento360::where('tipo', 'taxa_plataforma')->with('parcela')->orderBy('id')->get();
        $this->line($linhas->count().' parcelas com taxa da plataforma');

        if (! $this->option('simular')) {
            foreach ($linhas as $linha) {
                if ($linha->parcela) {
                    $contabil->registrar($linha->parcela, abs((int) $linha->valor_cents), $linha->ocorrido_em, $linha->descricao.': taxa da plataforma');
                }
            }
        }

        return self::SUCCESS;
    }
}
