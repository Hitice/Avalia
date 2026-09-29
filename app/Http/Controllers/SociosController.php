<?php

namespace App\Http\Controllers;

use App\Actions\Socios\EstornarLancamento;
use App\Actions\Socios\RegistrarLancamento;
use App\Enums\NaturezaLancamento;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\Socio;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * O caixa da sociedade.
 *
 * Responde quatro perguntas, e so elas: quanto ha em caixa, quanto cada socio
 * pos, quanto a empresa deve a cada um, e o que sobrou no mes depois de tudo.
 *
 * Resultado aqui NAO e o lucro dos produtos. Aquele e por unidade vendida e
 * ignora custo fixo; este soma receita e despesa de verdade, e e por isso que
 * so as naturezas que `afetaResultado` marca entram na conta.
 */
class SociosController extends Controller
{
    public function index(Request $pedido)
    {
        $competencia = $this->competencia($pedido);

        $socios = Socio::where('ativo', true)->orderBy('id')->get();
        $contas = ContaFinanceira::where('ativa', true)->orderBy('grupo')->orderBy('codigo')->get();

        $doMes = LancamentoFinanceiro::daCompetencia($competencia)
            ->with(['partidas.conta', 'staff:id,nome', 'estorna:id'])
            ->orderByDesc('ocorrido_em')->orderByDesc('id')
            ->get();

        return view('paginas.socios.index', [
            'competencia' => $competencia,
            'competencias' => $this->competencias(),

            'caixa' => $contas->where('grupo', 'ativo')->sum(fn (ContaFinanceira $c) => $c->saldoCents()),

            // Resultado do MES, e nao de sempre: e a pergunta que se faz ao
            // fechar. Receita e despesa sao as unicas que entram.
            'receita' => $this->doGrupo($doMes, 'receita'),
            'despesa' => $this->doGrupo($doMes, 'despesa'),

            'porSocio' => $socios->map(fn (Socio $socio) => [
                'nome' => $socio->nome,
                'aportou' => $this->saldoDoSocio($socio, ContaFinanceira::APORTE),
                'a_devolver' => $this->saldoDoSocio($socio, ContaFinanceira::EMPRESTIMO),
            ]),

            'contas' => $contas,
            'socios' => $socios,
            'lancamentos' => $doMes,
            'naturezas' => NaturezaLancamento::rotulos(),
        ]);
    }

    public function registrar(Request $pedido, RegistrarLancamento $registrar)
    {
        $dados = $pedido->validate([
            'natureza' => ['required', 'string'],
            'descricao' => ['required', 'string', 'max:200'],
            'valor' => ['required', 'string', 'max:20'],
            'ocorrido_em' => ['required', 'date'],
            'socio_id' => ['nullable', 'integer', 'exists:socios,id'],
            'conta_id' => ['nullable', 'integer', 'exists:contas_financeiras,id'],
            'destino_id' => ['nullable', 'integer', 'exists:contas_financeiras,id'],
            'contraparte' => ['nullable', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:100'],
        ]);

        $natureza = NaturezaLancamento::tentar($dados['natureza']);

        abort_if($natureza === null, 422);

        $quando = Carbon::parse($dados['ocorrido_em']);

        $registrar($natureza, [
            'descricao' => $dados['descricao'],
            'valor_cents' => Dinheiro::paraCentavos($dados['valor']) ?? 0,

            // A competencia sai da data do fato. Campo proprio pediria uma
            // decisao a cada lancamento, e quase sempre a resposta e o mes em
            // que a coisa aconteceu.
            'competencia' => $quando->format('Y-m'),
            'ocorrido_em' => $quando->toDateString(),

            'socio_id' => $dados['socio_id'] ?? null,
            'conta_id' => $dados['conta_id'] ?? null,
            'destino_id' => $dados['destino_id'] ?? null,
            'contraparte' => $dados['contraparte'] ?? null,
            'documento' => $dados['documento'] ?? null,
        ]);

        return back()->with('ok', 'Lançamento registrado.');
    }

    public function estornar(Request $pedido, LancamentoFinanceiro $lancamento, EstornarLancamento $estornar)
    {
        $dados = $pedido->validate(['motivo' => ['required', 'string', 'max:200']]);

        $estornar($lancamento, $dados['motivo']);

        return back()->with('ok', 'Lançamento estornado. As duas linhas ficam no extrato.');
    }

    private function competencia(Request $pedido): string
    {
        $pedida = (string) $pedido->query('competencia', '');

        return preg_match('/^\d{4}-\d{2}$/', $pedida) ? $pedida : now()->format('Y-m');
    }

    /** @return list<string> */
    private function competencias(): array
    {
        $primeira = LancamentoFinanceiro::min('competencia') ?: now()->format('Y-m');

        $cursor = Carbon::createFromFormat('Y-m', $primeira)->startOfMonth();
        $fim = now()->startOfMonth();
        $meses = [];

        while ($cursor->lessThanOrEqualTo($fim)) {
            array_unshift($meses, $cursor->format('Y-m'));
            $cursor->addMonth();
        }

        return $meses;
    }

    /** @param \Illuminate\Support\Collection<int, LancamentoFinanceiro> $lancamentos */
    private function doGrupo($lancamentos, string $grupo): int
    {
        return (int) $lancamentos
            ->flatMap(fn (LancamentoFinanceiro $l) => $l->partidas)
            ->filter(fn ($partida) => $partida->conta->grupo === $grupo)
            ->sum(fn ($partida) => $grupo === 'despesa' ? $partida->valor_cents : -$partida->valor_cents);
    }

    private function saldoDoSocio(Socio $socio, string $prefixo): int
    {
        return ContaFinanceira::firstWhere('codigo', $prefixo.':'.$socio->id)?->saldoCents() ?? 0;
    }
}
