<?php

namespace App\Console\Commands;

use App\Crm\Contatos;
use Illuminate\Console\Command;

/** Casa os cadastros que ja existem com um contato, pela mesma regra de deduplicacao. Repetir nao duplica. */
class LastrearContatos extends Command
{
    protected $signature = 'avalia:lastrear-contatos {--simular : mostra a contagem, sem gravar}';

    protected $description = 'Da contato a todo cliente, negocio, lead, interessado e produtor que ainda nao tem';

    public function handle(): int
    {
        $total = 0;

        foreach ([\App\Models\Cliente::class, \App\Models\Negocio::class, \App\Models\Lead::class, \App\Models\Interessado::class, \App\Models\Produtor::class] as $modelo) {
            $sem = $modelo::whereNull('contato_id')->count();
            $this->line(sprintf('%-12s %d sem contato', class_basename($modelo), $sem));
            $total += $sem;

            if ($this->option('simular')) {
                continue;
            }

            $modelo::whereNull('contato_id')->orderBy('id')->chunkById(200, function ($lote) {
                foreach ($lote as $entidade) {
                    Contatos::vincular($entidade, $entidade->getAttribute('origem') ?: 'lastro');
                }
            });
        }

        $this->line(($this->option('simular') ? 'seriam vinculados ' : 'vinculados ').$total.'; contatos agora: '.\App\Models\Contato::count());

        return self::SUCCESS;
    }
}
