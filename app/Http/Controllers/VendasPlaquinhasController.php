<?php

namespace App\Http\Controllers;

use App\Models\Etiqueta;
use App\Models\Staff;
use App\Support\RepartePlaquinha;
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
        $socios = $this->socios();

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

            'porDia' => $this->porDia($mes),

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
     * Os socios do config, resolvidos em contas.
     *
     * A ORDEM do config e a ordem da divisao, e e o que mantem o centavo impar
     * sempre na mesma pessoa. Alfabetar aqui faria o centavo trocar de dono no
     * dia em que alguem renomeasse a conta.
     *
     * @return array{contas: Collection<int, Staff>, ids: list<int>, ausentes: list<string>}
     */
    private function socios(): array
    {
        $emails = collect(config('etiquetas.socios'))->map(fn ($e) => mb_strtolower(trim((string) $e)))->filter();

        $achadas = Staff::withTrashed()
            ->whereIn('email', $emails->all())
            ->get(['id', 'nome', 'email'])
            ->keyBy(fn (Staff $s) => mb_strtolower($s->email));

        $contas = $emails->map(fn (string $email) => $achadas->get($email))->filter()->values();

        return [
            'contas' => $contas,
            'ids' => $contas->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'ausentes' => $emails->reject(fn (string $email) => $achadas->has($email))->values()->all(),
        ];
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
        $pct = (int) config('etiquetas.comissao_pct');

        $totais = ['bruto' => 0, 'custo' => 0, 'liquido' => 0, 'comissao' => 0, 'lucro' => 0];
        $porVendedor = [];
        $semVendedor = 0;

        foreach ($vendas as $venda) {
            $vendedorId = $venda->vendedor_id === null ? null : (int) $venda->vendedor_id;
            $ehSocio = $vendedorId !== null && in_array($vendedorId, $sociosIds, true);

            // Placa sem vendedor nao gera comissao, do mesmo jeito que a de
            // socio: nao houve venda de ninguem. Comissionar uma venda orfa
            // criaria dinheiro sem destinatario, que sairia da divisao dos
            // socios e deixaria o total de comissoes maior que a soma das linhas
            // por vendedor, na mesma tela.
            $geraComissao = $vendedorId !== null && ! $ehSocio;

            $parte = RepartePlaquinha::de(
                (int) $venda->valor_cents,

                // Venda anterior a coluna de custo cai no config. Deixar zero
                // mostraria lucro inflado, que e o erro que engana.
                $venda->custo_cents === null ? (int) config('etiquetas.custo_cents') : (int) $venda->custo_cents,

                $geraComissao,
                $pct,
            );

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
        return $contas->map(fn (Staff $socio, int $posicao) => [
            'nome' => $socio->nome,
            'mes' => $mes[$posicao] ?? 0,
            'total' => $total[$posicao] ?? 0,
        ]);
    }

    /**
     * Placas por DIA do mes escolhido.
     *
     * Era por mes, e mes nao responde a pergunta de quem vende: a variacao util
     * esta dentro da semana, e ela desaparece quando trinta dias viram uma
     * barra. Todos os dias entram, inclusive os sem venda, porque buraco no meio
     * da serie e informacao.
     *
     * @return Collection<int, array{dia: int, rotulo: string, fimDeSemana: bool, placas: int, bruto: int}>
     */
    private function porDia(Carbon $mes): Collection
    {
        $vendas = Etiqueta::query()
            ->vendidasEntre($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth())
            ->get(['vendida_em', 'valor_cents'])
            ->groupBy(fn (Etiqueta $e) => (int) $e->vendida_em->day);

        $dias = collect();
        $cursor = $mes->copy()->startOfMonth();

        while ($cursor->month === $mes->month) {
            $doDia = $vendas->get($cursor->day, collect());

            $dias->push([
                'dia' => $cursor->day,
                'rotulo' => $cursor->format('d/m'),
                'fimDeSemana' => $cursor->isWeekend(),
                'placas' => $doDia->count(),
                'bruto' => (int) $doDia->sum('valor_cents'),
            ]);

            $cursor->addDay();
        }

        return $dias;
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
