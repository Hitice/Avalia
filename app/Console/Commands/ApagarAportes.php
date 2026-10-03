<?php

namespace App\Console\Commands;

use App\Enums\NaturezaLancamento;
use App\Models\LancamentoFinanceiro;
use App\Support\Auditar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apaga os aportes de capital e os estornos deles. Pedido do dono em
 * 02/10/2026: os aportes lancados eram de montagem, nao de dinheiro que
 * entrou. E apagar, e nao estornar, de proposito; fica o rastro na auditoria.
 */
class ApagarAportes extends Command
{
    protected $signature = 'avalia:apagar-aportes {--simular : lista o que sairia, sem gravar}';

    protected $description = 'Apaga todos os lancamentos de aporte de capital, com rastro na auditoria';

    public function handle(): int
    {
        $aportes = LancamentoFinanceiro::where('natureza', NaturezaLancamento::Aporte->value)->with('estornos')->orderBy('id')->get();

        foreach ($aportes as $a) {
            $this->line(sprintf('%s  %s  %s  %d', $a->ocorrido_em->format('d/m/Y'), $a->competencia, $a->descricao, $a->valorCents()));
        }

        $this->line($aportes->count().' aportes'.($this->option('simular') ? ' (simulação)' : ''));

        if ($this->option('simular') || $aportes->isEmpty()) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aportes) {
            foreach ($aportes as $a) {
                Auditar::registrar('socios.lancamento.excluido', null, ['natureza' => 'aporte', 'descricao' => $a->descricao, 'competencia' => $a->competencia, 'valor_cents' => $a->valorCents()]);
                $a->estornos()->delete();
                $a->delete();
            }
        });

        $this->line('apagados');

        return self::SUCCESS;
    }
}
