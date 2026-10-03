<?php

namespace App\Console\Commands;

use App\Models\Consulta;
use App\Models\Etiqueta;
use App\Models\Fatura;
use App\Models\LancamentoFinanceiro;
use App\Support\Auditar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apaga a operacao de um mes para recomecar do zero: lancamentos do razao da
 * competencia, as vendas de placa do mes (a placa fica, com codigo, destino e
 * redirecionamento; sai so o fato comercial), e as consultas e faturas da
 * competencia. Fatura com cobranca emitida no Asaas segura o comando: la fora
 * ela existe. Negocios, contatos, links, leads e a auditoria ficam.
 *
 * Pedido do dono em 02/10/2026: setembro foi montagem; outubro e o mes zero.
 */
class ZerarMes extends Command
{
    protected $signature = 'avalia:zerar-mes {competencia : AAAA-MM} {--simular : mostra as contagens, sem gravar} {--forcar : apaga mesmo com cobranca emitida}';

    protected $description = 'Apaga razao, vendas de placa, consultas e faturas de uma competencia';

    public function handle(): int
    {
        $competencia = (string) $this->argument('competencia');

        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            $this->error('Competência no formato AAAA-MM.');

            return self::FAILURE;
        }

        $inicio = \Illuminate\Support\Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $lancamentos = LancamentoFinanceiro::where('competencia', $competencia);
        $placas = Etiqueta::whereNotNull('vendida_em')->whereBetween('vendida_em', [$inicio, $fim]);
        $consultas = Consulta::where('competencia', $competencia);
        $faturas = Fatura::where('competencia', $competencia)->with('cobrancaAsaas');

        $emitidas = (clone $faturas)->get()->filter(fn (Fatura $f) => $f->cobrancaEmitida())->count();

        $this->line(sprintf('razão %d · placas vendidas %d · consultas %d · faturas %d (%d com cobrança emitida)',
            (clone $lancamentos)->count(), (clone $placas)->count(), (clone $consultas)->count(), (clone $faturas)->count(), $emitidas));

        if ($this->option('simular')) {
            return self::SUCCESS;
        }

        if ($emitidas > 0 && ! $this->option('forcar')) {
            $this->error('Há fatura com cobrança emitida no Asaas. Cancele lá antes, ou use --forcar.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($competencia, $lancamentos, $placas, $consultas, $faturas) {
            $contagens = [
                'razao' => (clone $lancamentos)->count(),
                'placas' => (clone $placas)->count(),
                'consultas' => (clone $consultas)->count(),
                'faturas' => (clone $faturas)->count(),
            ];

            // Estornos de outra competencia que apontam para o mes caem junto.
            LancamentoFinanceiro::whereIn('estorna_id', (clone $lancamentos)->select('id'))->delete();
            $lancamentos->delete();

            $placas->update([
                'vendida_em' => null, 'valor_cents' => null, 'custo_cents' => null, 'vendedor_id' => null,
                'vence_em' => null, 'avisada_em' => null, 'comissao_paga_em' => null,
            ]);

            foreach ((clone $faturas)->get() as $fatura) {
                $fatura->cobrancaAsaas()->delete();
                $fatura->itens()->delete();
                $fatura->delete();
            }

            $consultas->delete();

            Auditar::registrar('mes.zerado', null, ['competencia' => $competencia] + $contagens);
        });

        $this->line('apagado');

        return self::SUCCESS;
    }
}
