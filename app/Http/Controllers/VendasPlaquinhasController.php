<?php

namespace App\Http\Controllers;

use App\Models\Etiqueta;
use App\Models\Staff;
use App\Support\RepartePlaquinha;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Quanto as plaquinhas venderam, e de quem e cada parte.
 *
 * Existe porque a venda da placa nao passa por fatura: ela e cobrada na hora,
 * fora do ciclo de consumo do resto da casa, e por isso nao aparecia em
 * nenhuma das telas de dinheiro. O repasse estava sendo somado a mao.
 *
 * A conta roda LINHA A LINHA, em PHP, e nao em SUM() no banco. Sao dezenas de
 * placas por mes, entao o custo e irrelevante, e a vantagem e grande: e
 * exatamente o mesmo App\Support\RepartePlaquinha que decide o repasse de uma
 * venda isolada. Somar no SQL seria uma segunda implementacao da mesma regra,
 * e duas contas para o mesmo dinheiro divergem no primeiro arredondamento.
 *
 * So administracao entra. O vendedor ve a comissao dele na propria tela; esta
 * aqui mostra a margem da casa e a divisao entre os socios, que nao e assunto
 * de quem vende.
 */
class VendasPlaquinhasController extends Controller
{
    public function __invoke(Request $request)
    {
        $mes = $this->mesPedido($request);
        $socios = $this->socios();

        $vendas = Etiqueta::query()
            ->vendidasEntre($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth())
            ->with('vendedor:id,nome,email')
            ->orderByDesc('vendida_em')
            ->get();

        $apuracao = $this->apurar($vendas, $socios['ids']);

        return view('paginas.plaquinhas.vendas', [
            'mes' => $mes,
            'meses' => $this->mesesComVenda(),

            'placas' => $vendas->count(),
            'totais' => $apuracao['totais'],
            'porVendedor' => $apuracao['porVendedor'],
            'porSocio' => $this->nomearSocios($socios['contas'], $apuracao['porSocio']),
            'semVendedor' => $apuracao['semVendedor'],

            // Lista errada de socio vira repasse errado, entao ela aparece em
            // tela em vez de falhar calada.
            'sociosAusentes' => $socios['ausentes'],

            'serie' => $this->serie(),
            'ultimas' => $vendas->take(10),
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
     * sempre na mesma pessoa. Alfabetar aqui faria a sobra trocar de dono no
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
     * Soma o reparte de cada venda do periodo.
     *
     * @param  Collection<int, Etiqueta>  $vendas
     * @param  list<int>  $sociosIds
     * @return array{totais: array<string, int>, porVendedor: Collection<int, array<string, mixed>>, porSocio: list<int>, semVendedor: int}
     */
    private function apurar(Collection $vendas, array $sociosIds): array
    {
        $pct = (int) config('etiquetas.comissao_pct');
        $quantosSocios = count($sociosIds);

        $totais = ['bruto' => 0, 'custo' => 0, 'liquido' => 0, 'comissao' => 0, 'sobra' => 0];
        $porVendedor = [];
        $semVendedor = 0;

        foreach ($vendas as $venda) {
            $vendedorId = $venda->vendedor_id === null ? null : (int) $venda->vendedor_id;
            $ehSocio = $vendedorId !== null && in_array($vendedorId, $sociosIds, true);

            $parte = RepartePlaquinha::de(
                (int) $venda->valor_cents,

                // Venda anterior a coluna de custo cai no config. Acontece so
                // se a migration de recuperacao nao tiver rodado; deixar zero
                // mostraria lucro inflado, que e o erro que engana.
                $venda->custo_cents === null ? (int) config('etiquetas.custo_cents') : (int) $venda->custo_cents,

                $ehSocio,
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

            // A divisao acontece UMA vez, sobre a sobra ja somada do mes.
            // Dividir venda a venda daria o centavo impar sempre ao primeiro
            // socio, e o vies acumularia placa a placa.
            'porSocio' => RepartePlaquinha::dividir($totais['sobra'], $quantosSocios),

            'semVendedor' => $semVendedor,
        ];
    }

    /**
     * Junta nome e valor de cada socio, na ordem da divisao.
     *
     * @param  Collection<int, Staff>  $contas
     * @param  list<int>  $valores
     * @return Collection<int, array{nome: string, email: string, cents: int}>
     */
    private function nomearSocios(Collection $contas, array $valores): Collection
    {
        return $contas->map(fn (Staff $socio, int $posicao) => [
            'nome' => $socio->nome,
            'email' => $socio->email,
            'cents' => $valores[$posicao] ?? 0,
        ]);
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

    /**
     * Placas e faturamento dos ultimos doze meses.
     *
     * Uma consulta agrupada, e nao doze: o grafico e ilustrativo e nao paga
     * ninguem, entao aqui SUM() no banco e suficiente e nao concorre com a
     * apuracao linha a linha.
     *
     * @return Collection<int, array{rotulo: string, placas: int, bruto: int}>
     */
    private function serie(): Collection
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(11);

        $linhas = Etiqueta::query()
            ->whereNotNull('vendida_em')
            ->where('vendida_em', '>=', $desde)
            ->get(['vendida_em', 'valor_cents'])
            ->groupBy(fn (Etiqueta $e) => $e->vendida_em->format('Y-m'));

        $serie = collect();
        $cursor = $desde->copy();

        while ($cursor->lessThanOrEqualTo(Carbon::now()->startOfMonth())) {
            $doMes = $linhas->get($cursor->format('Y-m'), collect());

            $serie->push([
                'rotulo' => $cursor->translatedFormat('M/y'),
                'placas' => $doMes->count(),
                'bruto' => (int) $doMes->sum('valor_cents'),
            ]);

            $cursor->addMonth();
        }

        return $serie;
    }
}
