<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\PagarComissao;
use App\Actions\Etiquetas\VendaNoRazao;
use App\Actions\Financeiro\Repasses;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\Staff;
use App\Support\Dinheiro;
use App\Support\RepartePlaquinha;
use App\Support\SociosDaPlaquinha;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * O caixa das plaquinhas: quanto entrou, quanto sobrou e de quem e.
 *
 * Existe porque a venda da placa nao passa por fatura: ela e cobrada na hora,
 * fora do ciclo de consumo do resto da casa, e por isso nao aparecia em nenhuma
 * das telas de dinheiro. O repasse estava sendo somado a mao.
 *
 * O negocio tem quatro linhas, nesta ordem, e a tela segue ela:
 *
 *   receita    o que o cliente pagou, gravado na venda
 *   custo      a placa fisica, desembolso direto de cada unidade
 *   comissao   25% do que resta, de quem vendeu
 *   lucro      o que fica, dividido entre os socios
 *
 * `lucro` aqui e lucro DESTE produto, antes de custo fixo e imposto: hospedagem,
 * dominio e ferramenta nao entram, porque nao sao por placa. Quem olhar isto como
 * resultado da empresa vai superestimar.
 *
 * A conta roda LINHA A LINHA, em PHP, e nao em SUM() no banco. Sao dezenas de
 * placas, entao o custo e irrelevante, e a vantagem e grande: e exatamente o
 * mesmo App\Support\RepartePlaquinha que decide o repasse de uma venda isolada.
 * Somar no SQL seria uma segunda implementacao da mesma regra, e duas contas para
 * o mesmo dinheiro divergem no primeiro arredondamento.
 *
 * So administracao entra. O vendedor ve a comissao dele na propria tela; esta
 * aqui mostra custo e divisao entre socios, que nao e assunto de quem vende.
 */
class VendasPlaquinhasController extends Controller
{
    public function __invoke(Request $request)
    {
        $mes = $this->mesPedido($request);
        $socios = SociosDaPlaquinha::resolver();

        $doMes = Etiqueta::query()
            ->vendidasEntre($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth())
            ->with('vendedor:id,nome')
            ->orderByDesc('vendida_em')
            ->get();

        $apuracao = $this->apurar($doMes, $socios['ids']);

        // O acumulado nao e a soma dos meses mostrados: e tudo que ja foi
        // vendido. Serve a pergunta "quanto esse produto deu ate hoje", que o
        // recorte do mes nao responde.
        $desdeSempre = $this->apurar(
            Etiqueta::query()->whereNotNull('vendida_em')->get(['valor_cents', 'custo_cents', 'vendedor_id']),
            $socios['ids'],
        );

        return view('paginas.plaquinhas.vendas', [
            'mes' => $mes,
            'meses' => $this->mesesComVenda(),

            'placas' => $doMes->count(),
            'placasTotal' => Etiqueta::whereNotNull('vendida_em')->count(),

            'totais' => $apuracao['totais'],
            'total' => $desdeSempre['totais'],

            'porVendedor' => $apuracao['porVendedor'],
            'semVendedor' => $apuracao['semVendedor'],

            'porSocio' => $this->nomearSocios(
                $socios['contas'],
                $apuracao['porSocio'],
                $desdeSempre['porSocio'],
            ),

            // Lista errada de socio vira repasse errado, entao ela aparece em
            // tela em vez de falhar calada.
            'sociosAusentes' => $socios['ausentes'],

            'porDia' => Etiqueta::vendasPorDia($mes),

            // Em aberto desde sempre, e nao so do mes: a sexta paga o que ficou.
            'aPagar' => Repasses::comissoesSales(),

            // O mes inteiro, e nao as dez ultimas: esta tabela e a leitura
            // alternativa dos graficos, para quem confere numero a numero ou usa
            // leitor de tela. Cortar a lista tiraria justamente o que ela serve.
            'vendas' => $doMes,
        ]);
    }

    /**
     * O mes em foco. Sem parametro, o corrente.
     *
     * Mes invalido na URL cai no corrente em vez de estourar: este endereco vai
     * parar em favorito e em link colado no WhatsApp.
     */
    private function mesPedido(Request $request): Carbon
    {
        $pedido = (string) $request->query('mes', '');

        try {
            return $pedido === ''
                ? Carbon::now()->startOfMonth()
                : Carbon::createFromFormat('Y-m', $pedido)->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }

    /**
     * Soma o reparte de cada venda do conjunto.
     *
     * @param  Collection<int, Etiqueta>  $vendas
     * @param  list<int>  $sociosIds
     * @return array{totais: array<string, int>, porVendedor: Collection<int, array<string, mixed>>, porSocio: list<int>, semVendedor: int}
     */
    private function apurar(Collection $vendas, array $sociosIds): array
    {

        $totais = ['bruto' => 0, 'custo' => 0, 'liquido' => 0, 'comissao' => 0, 'lucro' => 0];
        $porVendedor = [];
        $semVendedor = 0;

        foreach ($vendas as $venda) {
            $vendedorId = $venda->vendedor_id === null ? null : (int) $venda->vendedor_id;
            $ehSocio = $vendedorId !== null && in_array($vendedorId, $sociosIds, true);

            // A regra de quem comissiona mora em VendaNoRazao, que e tambem
            // quem lanca a venda no razao. Repetida aqui, a tela e o extrato
            // passariam a discordar no dia em que uma das duas mudasse.
            $parte = VendaNoRazao::reparte($venda, $sociosIds);

            foreach ($totais as $chave => $acumulado) {
                $totais[$chave] = $acumulado + $parte[$chave];
            }

            if ($vendedorId === null) {
                $semVendedor++;

                continue;
            }

            $porVendedor[$vendedorId] ??= [
                'id' => $vendedorId,
                'nome' => $venda->vendedor?->nome ?? 'Conta removida',
                'eh_socio' => $ehSocio,
                'placas' => 0,
                'bruto' => 0,
                'comissao' => 0,
            ];

            $porVendedor[$vendedorId]['placas']++;
            $porVendedor[$vendedorId]['bruto'] += $parte['bruto'];
            $porVendedor[$vendedorId]['comissao'] += $parte['comissao'];
        }

        return [
            'totais' => $totais,
            'porVendedor' => collect($porVendedor)->sortByDesc('placas')->values(),

            // A divisao acontece UMA vez, sobre o lucro ja somado. Dividir venda
            // a venda daria o centavo impar sempre ao primeiro socio, e o vies
            // acumularia placa a placa.
            'porSocio' => RepartePlaquinha::dividir($totais['lucro'], count($sociosIds)),

            'semVendedor' => $semVendedor,
        ];
    }

    /**
     * Nome de cada socio com a parte do mes e a acumulada, na ordem da divisao.
     *
     * @param  Collection<int, Staff>  $contas
     * @param  list<int>  $mes
     * @param  list<int>  $total
     * @return Collection<int, array{nome: string, mes: int, total: int}>
     */
    private function nomearSocios(Collection $contas, array $mes, array $total): Collection
    {
        $pct = (int) config('etiquetas.retencao_pct');

        return $contas->map(function (Staff $socio, int $posicao) use ($mes, $total, $pct) {
            // `mes` e `total` seguem sendo a parte inteira; o que sai e o pro-labore.
            $doMes = RepartePlaquinha::retencao($mes[$posicao] ?? 0, $pct);
            $deSempre = RepartePlaquinha::retencao($total[$posicao] ?? 0, $pct);

            return [
                'nome' => $socio->nome,
                'mes' => $mes[$posicao] ?? 0,
                'total' => $total[$posicao] ?? 0,
                'retido' => $doMes['retido'],
                'prolabore' => $doMes['prolabore'],
                'retidoTotal' => $deSempre['retido'],
                'prolaboreTotal' => $deSempre['prolabore'],
            ];
        });
    }

    public function pagarComissao(Staff $vendedor, PagarComissao $pagar)
    {
        try {
            $pago = $pagar($vendedor);
        } catch (Recusa $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Comissão de '.$vendedor->nome.' paga: '.Dinheiro::brl($pago['cents']).' por '.$pago['placas'].' '.($pago['placas'] === 1 ? 'placa' : 'placas').'.');
    }

    /**
     * Os meses que tem venda, para o seletor.
     *
     * Sai do banco e nao de um range fixo: mes vazio no seletor e clique que
     * leva a tela em branco, e o operador conclui que a tela quebrou.
     *
     * @return Collection<int, Carbon>
     */
    private function mesesComVenda(): Collection
    {
        $primeira = Etiqueta::whereNotNull('vendida_em')->min('vendida_em');

        $inicio = $primeira ? Carbon::parse($primeira)->startOfMonth() : Carbon::now()->startOfMonth();
        $fim = Carbon::now()->startOfMonth();

        $meses = collect();

        while ($inicio->lessThanOrEqualTo($fim)) {
            $meses->prepend($inicio->copy());
            $inicio->addMonth();
        }

        return $meses;
    }
}
