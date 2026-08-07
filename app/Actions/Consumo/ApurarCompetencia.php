<?php

namespace App\Actions\Consumo;

use App\Models\Cliente;
use App\Models\Consulta;

/**
 * A conta do mes de uma empresa: franquia, excedente e custo, servico a servico.
 *
 * Um lugar so para a mesma matematica que aparece em tres telas: o fechamento
 * que emite a fatura, o painel do cliente que mostra quanto falta para o
 * minimo e a previa de qualquer competencia. Tres copias da conta seriam tres
 * numeros diferentes no primeiro ajuste.
 *
 * So consulta concluida entra: a promessa da tela e "nao concluida, sem
 * cobranca", e isso inclui nao ocupar vaga de franquia. Uma falha contada na
 * quantidade empurraria uma consulta boa para o excedente cobrado.
 */
class ApurarCompetencia
{
    /**
     * @return array{
     *     itens: list<array<string, mixed>>,
     *     bruto: int, franquia: int, excedente: int, custo: int
     * }
     */
    public function __invoke(Cliente $cliente, string $competencia): array
    {
        $consultas = Consulta::where('cliente_id', $cliente->id)
            ->where('competencia', $competencia)
            ->where('situacao', Consulta::SUCESSO)
            ->get();

        $franquias = $cliente->plano
            ? $cliente->plano->franquias()->pluck('quantidade', 'servico_id')
            : collect();

        $itens = [];
        $bruto = 0;
        $franquia = 0;
        $excedente = 0;

        // A franquia é aplicada serviço a serviço e em quantidade. Fazê-la
        // sobre a soma em reais permitiria que um serviço barato cobrisse um
        // caro, contrariando o que foi contratado.
        foreach ($consultas->groupBy('servico_id') as $servicoId => $grupo) {
            $grupo = $grupo->sortBy('id')->values();
            $quantidade = $grupo->count();
            $incluidas = min($quantidade, (int) ($franquias[$servicoId] ?? 0));
            $valorBruto = (int) $grupo->sum('preco_cents');
            $valorFranquia = (int) $grupo->take($incluidas)->sum('preco_cents');
            $valorExcedente = (int) $grupo->skip($incluidas)->sum('preco_cents');

            $bruto += $valorBruto;
            $franquia += $valorFranquia;
            $excedente += $valorExcedente;
            $itens[] = [
                'servico_id' => $servicoId,
                'servico_nome' => $grupo->first()->servico?->nome ?? 'Serviço',
                'quantidade' => $quantidade,
                'quantidade_franquia' => $incluidas,
                'quantidade_excedente' => $quantidade - $incluidas,
                'valor_bruto_cents' => $valorBruto,
                'valor_franquia_cents' => $valorFranquia,
                'valor_excedente_cents' => $valorExcedente,
                'custo_cents' => (int) $grupo->sum('custo_cents'),
            ];
        }

        return [
            'itens' => $itens,
            'bruto' => $bruto,
            'franquia' => $franquia,
            'excedente' => $excedente,
            'custo' => (int) $consultas->sum('custo_cents'),
        ];
    }
}
