<?php

namespace App\Console\Commands;

use App\Actions\Etiquetas\VendaNoRazao;
use App\Models\ContaFinanceira;
use App\Models\Etiqueta;
use App\Support\SociosDaPlaquinha;
use Illuminate\Console\Command;

/**
 * Poe no razao as vendas de plaquinha anteriores as contas delas (Fase 3 do
 * PDD, secao 15).
 *
 * LE as etiquetas e nunca escreve nelas: QR impresso aponta para link publicado.
 *
 * Repetivel, com o indice unico `(origem_tipo, origem_id)` como arbitro. Importa
 * porque o cron de minuto roda mais de uma vez antes de ser apagado.
 */
class LastrearPlaquinhas extends Command
{
    protected $signature = 'avalia:lastrear-plaquinhas {--simular : mostra o que faria, sem gravar}';

    protected $description = 'Lanca no razao as vendas de plaquinha ja registradas';

    public function handle(VendaNoRazao $venda): int
    {
        foreach (['receita:plaquinha', 'custo:plaquinha', 'comissao', 'comissao-a-pagar', 'caixa'] as $codigo) {
            if (! ContaFinanceira::where('codigo', $codigo)->exists()) {
                $this->error("A conta {$codigo} não está cadastrada. Rode as migrations antes.");

                return self::FAILURE;
            }
        }

        $socios = SociosDaPlaquinha::resolver();

        if ($socios['ausentes'] !== []) {
            // Sem a conta do socio, a venda dele comissionaria, e a comissao
            // iria para um passivo que ninguem deve. Melhor parar.
            $this->error('Sócio sem conta na equipe: '.implode(', ', $socios['ausentes']));

            return self::FAILURE;
        }

        $vendas = Etiqueta::whereNotNull('vendida_em')
            ->whereNotNull('valor_cents')
            ->orderBy('vendida_em')
            ->get();

        $lancadas = 0;
        $repetidas = 0;

        foreach ($vendas as $etiqueta) {
            $parte = VendaNoRazao::reparte($etiqueta, $socios['ids']);

            if ($this->option('simular')) {
                $this->line(sprintf(
                    '%s  %s  bruto %d  custo %d  comissao %d',
                    $etiqueta->vendida_em->format('Y-m-d'),
                    str_pad((string) $etiqueta->codigo, 12),
                    $parte['bruto'],
                    $parte['custo'],
                    $parte['comissao'],
                ));

                continue;
            }

            $venda->registrar($etiqueta, $socios['ids']) === null ? $repetidas++ : $lancadas++;
        }

        $this->line($this->option('simular')
            ? $vendas->count().' vendas seriam lancadas'
            : "lancadas {$lancadas}, ja estavam no razao {$repetidas}");

        return self::SUCCESS;
    }
}
