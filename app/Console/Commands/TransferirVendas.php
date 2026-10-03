<?php

namespace App\Console\Commands;

use App\Exceptions\Recusa;
use App\Models\Staff;
use Illuminate\Console\Command;

/** A mesma transferencia da tela de Equipe, para quem opera por cron. */
class TransferirVendas extends Command
{
    protected $signature = 'avalia:transferir-vendas {de : e-mail de quem vendeu} {para : e-mail de quem passa a ser o vendedor} {--carteira : move tambem clientes e faturas do One} {--simular : mostra as contagens, sem gravar}';

    protected $description = 'Passa placas, negocios e, se pedido, a carteira do One de uma conta para outra';

    public function handle(\App\Actions\Equipe\TransferirVendas $transferir): int
    {
        $de = Staff::withTrashed()->firstWhere('email', mb_strtolower(trim($this->argument('de'))));
        $para = Staff::firstWhere('email', mb_strtolower(trim($this->argument('para'))));

        if (! $de || ! $para) {
            $this->error('Conta não encontrada: '.(! $de ? $this->argument('de') : $this->argument('para')));

            return self::FAILURE;
        }

        try {
            $contagens = $transferir($de, $para, (bool) $this->option('carteira'), (bool) $this->option('simular'));
        } catch (Recusa $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($contagens as $rotulo => $n) {
            $this->line(sprintf('%-16s %d', $rotulo, $n));
        }

        $this->line($this->option('simular') ? 'simulação, nada gravado' : 'transferido de '.$de->nome.' para '.$para->nome.'. Rode avalia:relancar-plaquinhas.');

        return self::SUCCESS;
    }
}
