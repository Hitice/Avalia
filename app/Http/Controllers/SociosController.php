<?php

namespace App\Http\Controllers;

use App\Actions\Planilha\MontarPlanilhaCaixa;
use App\Actions\Socios\EstornarLancamento;
use App\Actions\Socios\ExcluirLancamento;
use App\Actions\Socios\RegistrarLancamento;
use App\Contabil\Competencia;
use App\Contabil\LivroCaixa;
use App\Enums\NaturezaLancamento;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\Socio;
use App\Support\Auditar;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $competencia = Competencia::pedida($pedido->query('competencia'));

        $socios = Socio::where('ativo', true)->orderBy('id')->get();
        $contas = ContaFinanceira::where('ativa', true)->orderBy('grupo')->orderBy('codigo')->get();

        $doMes = LancamentoFinanceiro::daCompetencia($competencia)
            ->with(['partidas.conta', 'staff:id,nome', 'estorna:id'])
            ->orderByDesc('ocorrido_em')->orderByDesc('id')
            ->get();

        $caixa = LivroCaixa::doMes($doMes, $competencia);

        return view('paginas.socios.index', [
            'competencia' => $competencia,
            'competencias' => Competencia::existentes(),
            'movimentos' => $caixa['movimentos'],
            'entradas' => $caixa['entradas'],
            'saidas' => $caixa['saidas'],
            'porCategoria' => LivroCaixa::porCategoria($doMes),

            'caixa' => $contas->where('grupo', 'ativo')->sum(fn (ContaFinanceira $c) => $c->saldoCents()),

            // Resultado do MES, e nao de sempre: e a pergunta que se faz ao
            // fechar. Receita e despesa sao as unicas que entram.
            'receita' => $this->doGrupo($doMes, 'receita'),
            'despesa' => $this->doGrupo($doMes, 'despesa'),

            'porSocio' => $socios->map(fn (Socio $socio) => [
                'id' => $socio->id,
                'nome' => $socio->nome,
                'conta' => $socio->staff?->email,
                'participacao' => $socio->participacao_bps,
                // So se remove socio sem lancamento: o que tem partida fica, para o extrato se explicar.
                'removivel' => ! \App\Models\PartidaFinanceira::whereIn('conta_id', $socio->contas()->select('id'))->exists(),
                'aportou' => $this->saldoDoSocio($socio, ContaFinanceira::APORTE),
                'a_devolver' => $this->saldoDoSocio($socio, ContaFinanceira::EMPRESTIMO),
            ]),

            'contas' => $contas,
            'socios' => $socios,
            'lancamentos' => $doMes,
            'naturezas' => NaturezaLancamento::rotulosManuais(),

            // Para ligar o socio a uma conta de acesso, quando ele tiver uma.
            'equipe' => \App\Models\Staff::orderBy('nome')->get(['id', 'nome']),

            // A soma das participacoes, conferida na tela e nao no banco.
            'participacaoTotal' => $socios->sum('participacao_bps'),
        ]);
    }

    /**
     * Cadastra um socio.
     *
     * Sem isto o modulo subiu sem porta de entrada: seis das nove naturezas
     * exigem socio, e nao havia como criar o primeiro.
     *
     * A participacao e opcional e nasce zero. Exigir que a soma feche 100% no
     * cadastro impediria gravar o primeiro socio, que sozinho nunca fecha
     * enquanto o segundo nao entra; quem confere a soma e a tela.
     */
    public function criarSocio(Request $pedido)
    {
        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'max:120'],
            'participacao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);

        Socio::create([
            'nome' => $dados['nome'],

            // Em pontos-base, como o resto do dinheiro desta casa: 50% vira
            // 5000, e meio por cento continua representavel.
            'participacao_bps' => (int) round(((float) ($dados['participacao'] ?? 0)) * 100),

            'staff_id' => $dados['staff_id'] ?? null,
            'ativo' => true,
        ]);

        Auditar::registrar('socios.socio.criado', null, ['nome' => $dados['nome']]);

        return back()->with('ok', 'Sócio cadastrado.');
    }

    /** Corrige a participacao de um socio, em pontos-base como no cadastro. */
    public function participacao(Request $pedido, Socio $socio)
    {
        $dados = $pedido->validate(['participacao' => ['required', 'numeric', 'min:0', 'max:100']]);
        $socio->update(['participacao_bps' => (int) round(((float) $dados['participacao']) * 100)]);
        Auditar::registrar('socios.socio.alterado', $socio, ['participacao_bps' => $socio->participacao_bps]);

        return back()->with('ok', 'Participação de '.$socio->nome.': '.number_format($socio->participacao_bps / 100, 2, ',', '.').'%.');
    }

    /** Remove um socio cadastrado por engano. Com lancamento, nao sai: o extrato aponta para ele. */
    public function removerSocio(Socio $socio)
    {
        if (\App\Models\PartidaFinanceira::whereIn('conta_id', $socio->contas()->select('id'))->exists()) {
            return back()->with('erro', $socio->nome.' tem lançamentos e não se remove.');
        }

        \App\Support\Auditar::registrar('socios.socio.removido', $socio, ['nome' => $socio->nome]);
        $socio->contas()->delete();
        $socio->delete();

        return back()->with('ok', 'Sócio '.$socio->nome.' removido.');
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
            'categoria_id' => ['nullable', 'integer', 'exists:contas_financeiras,id'],
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
            'categoria_id' => $dados['categoria_id'] ?? null,
            'destino_id' => $dados['destino_id'] ?? null,
            'contraparte' => $dados['contraparte'] ?? null,
            'documento' => $dados['documento'] ?? null,
        ]);

        // A confirmacao diz o EFEITO, e nao que gravou. E o unico momento em
        // que da para perceber que a natureza escolhida nao era a pretendida,
        // e escolher errado aqui e o erro que o modulo existe para evitar.
        $socio = ($dados['socio_id'] ?? null) ? Socio::find($dados['socio_id'])?->nome : null;

        return back()->with('ok', str_replace(
            ['{valor}', '{socio}'],
            [Dinheiro::brl(Dinheiro::paraCentavos($dados['valor']) ?? 0), $socio ?? 'o sócio'],
            $natureza->efeito(),
        ));
    }

    /**
     * Apaga um lancamento digitado por engano.
     *
     * A guarda vive na Action. Aqui so o fluxo: quem apagou ja sabe o que
     * apagou, entao a confirmacao e curta.
     */
    public function excluir(LancamentoFinanceiro $lancamento, ExcluirLancamento $excluir)
    {
        $excluir($lancamento);

        return back()->with('ok', 'Lançamento apagado.');
    }

    public function estornar(Request $pedido, LancamentoFinanceiro $lancamento, EstornarLancamento $estornar)
    {
        $dados = $pedido->validate(['motivo' => ['required', 'string', 'max:200']]);

        $estornar($lancamento, $dados['motivo']);

        return back()->with('ok', 'Lançamento estornado. As duas linhas ficam no extrato.');
    }

    /** O mes em planilha: o livro-caixa e o por categoria, para conferir fora do sistema. */
    public function planilha(Request $pedido, MontarPlanilhaCaixa $montar): StreamedResponse
    {
        $competencia = Competencia::pedida($pedido->query('competencia'));
        $doMes = LancamentoFinanceiro::daCompetencia($competencia)
            ->with(['partidas.conta', 'staff:id,nome'])->orderBy('ocorrido_em')->orderBy('id')->get();

        $conteudo = $montar($doMes, $competencia);
        Auditar::registrar('caixa.exportado', null, ['competencia' => $competencia, 'lancamentos' => $doMes->count()]);

        return response()->streamDownload(
            fn () => print $conteudo,
            'avalia-caixa-'.$competencia.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

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
