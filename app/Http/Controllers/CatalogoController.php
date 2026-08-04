<?php

namespace App\Http\Controllers;

use App\Models\Preco;
use App\Models\Servico;
use App\Models\VersaoCatalogo;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A tabela de precos da Avalia, editavel direto na tela.
 *
 * Um catalogo so: nao ha rascunho, ativacao nem copia. O que impede um
 * reajuste de hoje de mudar cobranca de ontem e a consulta e a fatura
 * gravarem preco e custo na emissao (PDD.md, secoes 7 e 8), nao travar esta
 * tabela.
 */
class CatalogoController extends Controller
{
    /** O que a matriz mostra. Venda e o dia a dia; custo e margem sao internos. */
    public const VISOES = [
        'venda' => 'Preço de venda',
        'custo' => 'Custo do fornecedor',
        'margem' => 'Margem',
    ];

    public function tabela(Request $request)
    {
        $catalogo = VersaoCatalogo::vigente();

        if (! $catalogo) {
            return view('paginas.catalogo.tabela', [
                'catalogo' => null,
                'faixas' => [],
                'linhas' => collect(),
                'categoria' => null,
                'visao' => 'venda',
            ]);
        }

        $categoria = $request->query('categoria');

        if (! array_key_exists($categoria, Servico::CATEGORIAS)) {
            $categoria = null;
        }

        $visao = $request->query('visao');

        if (! array_key_exists($visao, self::VISOES)) {
            $visao = 'venda';
        }

        $precos = $catalogo->precos()->with('servico')->get();

        // As faixas saem dos precos ja carregados. Perguntar faixas() ao
        // catalogo seria um SELECT a mais, e contra banco remoto cada ida e
        // volta custa quase meio segundo na cara do operador.
        $faixas = $precos
            ->pluck('consumo_minimo_cents')
            ->unique()
            ->sort()
            ->values()
            ->map(intval(...))
            ->all();

        // Uma linha por servico, com os precos indexados pela faixa. Assim a
        // tabela da tela e so um loop sobre as faixas.
        $linhas = $precos
            ->groupBy('servico_id')
            ->map(fn ($precos) => [
                'servico' => $precos->first()->servico,
                'precos' => $precos->keyBy('consumo_minimo_cents'),
            ])
            ->when($categoria, fn ($linhas) => $linhas->filter(
                fn ($linha) => $linha['servico']->categoria === $categoria
            ))
            ->sortBy(fn ($linha) => $linha['servico']->nome)
            ->values();

        return view('paginas.catalogo.tabela', [
            'catalogo' => $catalogo,
            'faixas' => $faixas,
            'linhas' => $linhas,
            'categoria' => $categoria,
            'visao' => $visao,
        ]);
    }

    /** Grava os precos de venda editados. */
    public function precos(Request $request, VersaoCatalogo $catalogo)
    {
        return $this->gravarColuna($request, $catalogo, 'precos', 'preco_cents', 'preco');
    }

    /**
     * Grava o custo do fornecedor.
     *
     * Aceita campo vazio: apagar o custo devolve a linha ao estado "custo nao
     * cadastrado", que a tela deixa em branco em vez de mostrar zero. Zero seria
     * mentira, porque significaria fornecedor de graca.
     */
    public function custos(Request $request, VersaoCatalogo $catalogo)
    {
        return $this->gravarColuna($request, $catalogo, 'custos', 'custo_cents', 'custo', true);
    }

    /** Aliquota de imposto usada no calculo de margem e de piso. */
    public function imposto(Request $request, VersaoCatalogo $catalogo)
    {
        $dados = $request->validate([
            'imposto' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $catalogo->update(['imposto_bps' => (int) round((float) $dados['imposto'] * 100)]);

        return back()->with('ok', "Imposto ajustado para {$catalogo->fresh()->impostoRotulo()}.");
    }

    /**
     * Escreve uma coluna de dinheiro da matriz em lote.
     *
     * Sao ate 301 linhas: uma consulta por linha contra banco remoto levaria
     * minutos. Grava so o que mudou, e so o que pertence a este catalogo: id
     * chutado no formulario nao reprecifica outra tabela.
     */
    private function gravarColuna(
        Request $request,
        VersaoCatalogo $catalogo,
        string $campo,
        string $coluna,
        string $rotulo,
        bool $aceitaVazio = false,
    ) {
        $request->validate([
            $campo => ['array'],
            $campo.'.*' => ['nullable', 'string', 'max:20'],
        ]);

        $informados = collect($request->input($campo, []))
            ->map(fn ($valor) => Dinheiro::paraCentavos($valor))
            ->reject(fn (?int $centavos) => $centavos !== null && $centavos < 0)
            ->when(! $aceitaVazio, fn ($valores) => $valores->filter(fn (?int $c) => $c !== null));

        $linhas = $catalogo->precos()
            ->whereIn('id', $informados->keys())
            ->get(['id', 'versao_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents', 'custo_cents'])
            ->filter(fn (Preco $preco) => $preco->{$coluna} !== $informados[$preco->id])
            ->map(fn (Preco $preco) => [
                'id' => $preco->id,
                'versao_id' => $preco->versao_id,
                'servico_id' => $preco->servico_id,
                'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                'preco_cents' => $preco->preco_cents,
                'custo_cents' => $preco->custo_cents,
                $coluna => $informados[$preco->id],
            ])
            ->values()
            ->all();

        if ($linhas === []) {
            return back()->with('ok', "Nenhum {$rotulo} mudou.");
        }

        Preco::upsert($linhas, ['id'], [$coluna]);

        return back()->with('ok', count($linhas)." {$rotulo}(s) atualizado(s).");
    }

    /**
     * Aplica um percentual sobre os precos.
     *
     * Um UPDATE so: reajuste linha a linha seriam 301 idas ao banco.
     */
    public function reajustar(Request $request, VersaoCatalogo $catalogo)
    {
        $dados = $request->validate([
            'percentual' => ['required', 'numeric', 'between:-90,900'],
            'categoria' => ['nullable', Rule::in(array_keys(Servico::CATEGORIAS))],
        ]);

        // %.6F garante ponto decimal e nada de notacao cientifica ao entrar na
        // SQL. O valor ja passou por 'numeric' e pela faixa, mas o formato e o
        // que impede uma surpresa de locale virar SQL invalida.
        $fator = sprintf('%.6F', 1 + ((float) $dados['percentual'] / 100));

        $afetados = $catalogo->precos()
            ->when(
                $dados['categoria'] ?? null,
                fn ($q, $categoria) => $q->whereHas('servico', fn ($s) => $s->where('categoria', $categoria)),
            )
            ->update([
                // Arredonda no banco para nao trazer 301 linhas ate o PHP.
                'preco_cents' => DB::raw('cast(round(preco_cents * '.$fator.') as integer)'),
                'updated_at' => now(),
            ]);

        return back()->with('ok', sprintf(
            '%s aplicado em %d preco(s).',
            ($dados['percentual'] > 0 ? '+' : '').$dados['percentual'].'%',
            $afetados,
        ));
    }
}
