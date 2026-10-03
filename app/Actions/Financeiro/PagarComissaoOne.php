<?php

namespace App\Actions\Financeiro;

use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Exceptions\Recusa;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * Paga ao vendedor a comissao de consultas liberada e ainda nao paga, ja
 * descontadas as demonstracoes que ele fez e ainda nao foram cobradas. Marca
 * fatura a fatura e consulta a consulta, e baixa comissao-a-pagar contra o
 * caixa. Demonstracao que passa da comissao fica para a proxima sexta.
 */
class PagarComissaoOne
{
    public function __construct(private Lancar $lancar) {}

    /** @return array{faturas: int, liberado: int, demonstracoes: int, cents: int} */
    public function __invoke(Staff $vendedor): array
    {
        return DB::transaction(function () use ($vendedor) {
            $faturas = Fatura::where('vendedor_id', $vendedor->id)->whereNotNull('comissao_liberada_em')
                ->whereNull('comissao_paga_em')->lockForUpdate()->get();
            $demos = Consulta::where('vendedor_id', $vendedor->id)->where('situacao', Consulta::SUCESSO)
                ->whereNull('descontada_em')->lockForUpdate()->get();

            $liberado = (int) $faturas->sum('comissao_cents');
            $desconto = (int) $demos->sum('custo_cents');
            $cents = $liberado - $desconto;

            if ($cents <= 0) {
                throw new Recusa('Nada a pagar para '.$vendedor->nome.': as demonstrações cobrem a comissão liberada.');
            }

            Fatura::whereIn('id', $faturas->pluck('id'))->update(['comissao_paga_em' => now()]);
            Consulta::whereIn('id', $demos->pluck('id'))->update(['descontada_em' => now()]);

            ($this->lancar)(Partidas::porCodigo(['comissao-a-pagar' => $cents, 'caixa' => -$cents]), [
                'natureza' => 'pagamento',
                'descricao' => 'Comissão de consultas paga a '.$vendedor->nome.', '.$faturas->count().' '.($faturas->count() === 1 ? 'fatura' : 'faturas'),
                'contraparte' => $vendedor->nome,
                'competencia' => now()->format('Y-m'),
                'ocorrido_em' => now()->toDateString(),
            ]);

            return ['faturas' => $faturas->count(), 'liberado' => $liberado, 'demonstracoes' => $desconto, 'cents' => $cents];
        });
    }
}
