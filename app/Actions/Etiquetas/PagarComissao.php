<?php

namespace App\Actions\Etiquetas;

use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\Staff;
use App\Support\SociosDaPlaquinha;
use Illuminate\Support\Facades\DB;

/**
 * Paga ao vendedor tudo que ele tem de comissao em aberto.
 *
 * Marca venda a venda e lanca no razao a saida: a divida em comissao-a-pagar
 * baixa e o caixa cai no mesmo valor. A despesa nao entra de novo, ja foi
 * reconhecida na venda. Tudo ou nada, com as linhas travadas, porque dois
 * cliques na sexta pagariam a mesma placa duas vezes.
 */
class PagarComissao
{
    public function __construct(private Lancar $lancar) {}

    /** @return array{placas: int, cents: int} */
    public function __invoke(Staff $vendedor): array
    {
        $socios = SociosDaPlaquinha::resolver()['ids'];

        if (in_array((int) $vendedor->id, $socios, true)) {
            throw new Recusa('Sócio não recebe comissão: a parte dele sai no split.');
        }

        return DB::transaction(function () use ($vendedor, $socios) {
            $vendas = Etiqueta::where('vendedor_id', $vendedor->id)->comissaoEmAberto()->lockForUpdate()->get();
            $total = 0;

            foreach ($vendas as $venda) {
                $total += VendaNoRazao::reparte($venda, $socios)['comissao'];
            }

            if ($total <= 0) {
                throw new Recusa('Nada a pagar para '.$vendedor->nome.'.');
            }

            Etiqueta::whereIn('id', $vendas->pluck('id'))->update(['comissao_paga_em' => now()]);

            $this->lancar(Partidas::porCodigo(['comissao-a-pagar' => $total, 'caixa' => -$total]), [
                'natureza' => 'pagamento',
                'descricao' => 'Comissão paga a '.$vendedor->nome.', '.$vendas->count().' '.($vendas->count() === 1 ? 'placa' : 'placas'),
                'contraparte' => $vendedor->nome,
                'competencia' => now()->format('Y-m'),
                'ocorrido_em' => now()->toDateString(),
            ]);

            return ['placas' => $vendas->count(), 'cents' => $total];
        });
    }

    private function lancar(Partidas $partidas, array $dados): void
    {
        ($this->lancar)($partidas, $dados);
    }
}
