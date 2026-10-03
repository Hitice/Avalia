<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Etiqueta;
use App\Models\Fatura;
use App\Models\Negocio;
use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Passa o que uma conta vendeu para outra: placas (vendidas, em maos e
 * geradas) e os negocios que ela cadastrou. A carteira do One (clientes e faturas) so com
 * --carteira, porque muda quem recebe comissao de fatura ja liberada.
 *
 * Existe porque a conta mestre (comercial@) vendeu no comeco, e a venda e do
 * socio, nao da conta de administracao. Depois, rode avalia:relancar-plaquinhas:
 * a comissao no razao depende de quem vendeu.
 */
class TransferirVendas extends Command
{
    protected $signature = 'avalia:transferir-vendas {de : e-mail de quem vendeu} {para : e-mail de quem passa a ser o vendedor} {--carteira : move tambem clientes e faturas do One} {--simular : mostra as contagens, sem gravar}';

    protected $description = 'Passa placas, negocios e, se pedido, a carteira do One de uma conta para outra';

    public function handle(): int
    {
        $de = Staff::withTrashed()->firstWhere('email', mb_strtolower(trim($this->argument('de'))));
        $para = Staff::firstWhere('email', mb_strtolower(trim($this->argument('para'))));

        if (! $de || ! $para) {
            $this->error('Conta não encontrada: '.(! $de ? $this->argument('de') : $this->argument('para')));

            return self::FAILURE;
        }

        $lotes = [
            'placas vendidas' => Etiqueta::where('vendedor_id', $de->id),
            'placas em mãos' => Etiqueta::where('consignada_para_id', $de->id),
            'placas geradas' => Etiqueta::where('staff_id', $de->id),
            'negócios' => Negocio::where('vendedor_id', $de->id),
        ];

        if ($this->option('carteira')) {
            $lotes['clientes'] = Cliente::withTrashed()->where('vendedor_id', $de->id);
            $lotes['faturas'] = Fatura::where('vendedor_id', $de->id);
        }

        $coluna = ['placas em mãos' => 'consignada_para_id', 'placas geradas' => 'staff_id'];
        $contagens = [];

        DB::transaction(function () use ($lotes, $coluna, $de, $para, &$contagens) {
            foreach ($lotes as $rotulo => $consulta) {
                $contagens[$rotulo] = (clone $consulta)->count();
                $this->line(sprintf('%-16s %d', $rotulo, $contagens[$rotulo]));

                if (! $this->option('simular')) {
                    $consulta->update([($coluna[$rotulo] ?? 'vendedor_id') => $para->id]);
                }
            }

            if (! $this->option('simular')) {
                Auditar::registrar('vendas.transferidas', $para, ['de' => $de->email, 'para' => $para->email] + $contagens);
            }
        });

        $this->line($this->option('simular') ? 'simulação, nada gravado' : 'transferido de '.$de->nome.' para '.$para->nome.'. Rode avalia:relancar-plaquinhas.');

        return self::SUCCESS;
    }
}
