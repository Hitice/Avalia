<?php

namespace App\Console\Commands;

use App\Actions\Etiquetas\VendaNoRazao;
use App\Actions\Socios\EstornarLancamento;
use App\Contabil\Lancar;
use App\Models\ContaFinanceira;
use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Support\SociosDaPlaquinha;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Relanca no razao as vendas de plaquinha cuja comissao mudou de regra.
 *
 * Em 02/10/2026 a comissao passou de 25% do liquido para 25% do valor de venda,
 * e as vendas ja lancadas ficaram com o numero antigo. Trocar a regra sem tocar
 * o razao faria o painel e o extrato discordarem.
 *
 * Estorna e relanca, como a correcao de valor: o original fica, com o estorno ao
 * lado, e o novo entra no mes atual. Idempotente: venda cuja comissao ja bate, ou
 * cujo original ja foi estornado, e pulada. Pode rodar duas vezes.
 */
class RelancarPlaquinhas extends Command
{
    protected $signature = 'avalia:relancar-plaquinhas {--simular : mostra o que faria, sem gravar}';

    protected $description = 'Relanca no razao as vendas de plaquinha cuja comissao mudou de regra';

    public function handle(EstornarLancamento $estornar, Lancar $lancar, VendaNoRazao $contabil): int
    {
        $comissao = ContaFinanceira::firstWhere('codigo', 'comissao');

        if (! $comissao) {
            $this->error('A conta comissao não está cadastrada.');

            return self::FAILURE;
        }

        $socios = SociosDaPlaquinha::resolver()['ids'];
        $relancadas = 0;
        $iguais = 0;

        $originais = LancamentoFinanceiro::where('origem_tipo', 'etiqueta')
            ->whereNull('estorna_id')->with('partidas')->orderBy('id')->get();

        foreach ($originais as $original) {
            $etiqueta = Etiqueta::find($original->origem_id);

            if ($original->estornado() || ! $etiqueta || $etiqueta->vendida_em === null) {
                continue;
            }

            $postada = (int) ($original->partidas->firstWhere('conta_id', $comissao->id)?->valor_cents ?? 0);
            $devida = (int) VendaNoRazao::reparte($etiqueta, $socios)['comissao'];

            if ($postada === $devida) {
                $iguais++;

                continue;
            }

            $this->line(sprintf('%s  comissao %d -> %d', $etiqueta->codigo, $postada, $devida));
            $relancadas++;

            if ($this->option('simular')) {
                continue;
            }

            DB::transaction(function () use ($estornar, $lancar, $contabil, $original, $etiqueta, $socios) {
                $estornar($original, 'Comissão sobre o valor de venda, regra de 02/10/2026');

                $lancar($contabil->partidas($etiqueta, $socios), [
                    'natureza' => 'receita',
                    'descricao' => 'Venda relançada da plaquinha '.$etiqueta->codigo,
                    'competencia' => now()->format('Y-m'),
                    'ocorrido_em' => now()->toDateString(),
                ]);
            });
        }

        $this->line($this->option('simular')
            ? "{$relancadas} seriam relançadas, {$iguais} já batem"
            : "relançadas {$relancadas}, já batiam {$iguais}");

        return self::SUCCESS;
    }
}
