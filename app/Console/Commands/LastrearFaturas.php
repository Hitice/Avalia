<?php

namespace App\Console\Commands;

use App\Actions\Financeiro\FaturaNoRazao;
use App\Contabil\Lancar;
use App\Models\Fatura;
use Illuminate\Console\Command;

/**
 * Poe no razao as faturas liquidadas que ficaram de fora, e completa as que
 * entraram so com a receita (antes de 02/10/2026 era assim). Le e nunca
 * escreve em faturas; repetir nao duplica, pela origem unica.
 */
class LastrearFaturas extends Command
{
    protected $signature = 'avalia:lastrear-faturas {--simular : mostra o que faria, sem gravar}';

    protected $description = 'Lanca no razao as faturas liquidadas que faltam, e completa as lancadas so com a receita';

    public function handle(FaturaNoRazao $contabil, Lancar $lancar): int
    {
        $novas = $completadas = 0;

        foreach (Fatura::whereNotNull('liquidada_em')->with('cliente:id,razao_social')->orderBy('id')->get() as $fatura) {
            $vivos = FaturaNoRazao::lancamentosDe($fatura)->reject(fn ($l) => $l->estornado());

            if ($vivos->isEmpty()) {
                $this->line("fatura {$fatura->id}  lancar inteira");
                $novas++;
                $this->option('simular') || $contabil->registrar($fatura, $fatura->liquidada_em);

                continue;
            }

            if ($vivos->first()->partidas()->count() > 2) {
                continue;
            }

            $this->line("fatura {$fatura->id}  completar imposto, custo e comissao");
            $completadas++;

            if (! $this->option('simular')) {
                $lancar->umaVez($contabil->complemento($fatura), [
                    'natureza' => 'receita',
                    'descricao' => 'Fatura '.$fatura->competencia.', imposto, custo e comissão',
                    'competencia' => $fatura->liquidada_em->format('Y-m'),
                    'ocorrido_em' => $fatura->liquidada_em->toDateString(),
                    'origem_tipo' => 'fatura-complemento',
                    'origem_id' => $fatura->id,
                ]);
            }
        }

        $this->line(($this->option('simular') ? 'seriam ' : '')."lancadas {$novas}, completadas {$completadas}");

        return self::SUCCESS;
    }
}
