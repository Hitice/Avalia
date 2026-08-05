<?php

namespace App\Http\Controllers;

use App\Enums\Categoria;
use App\Http\Requests\ParametrosCatalogoRequest;
use App\Models\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A tabela de precos da Avalia, editavel direto na tela.
 *
 * Um catalogo so: nao ha rascunho, ativacao nem copia. O que impede um reajuste
 * de hoje de mudar cobranca de ontem e a consulta e a fatura gravarem preco e
 * custo na emissao (PDD.md, secoes 7 e 8), nao travar esta tabela.
 *
 * Aqui so mora fluxo HTTP: montar a tela, chamar a acao, traduzir o resultado
 * em mensagem. Regra de preco vive em App\Actions\Catalogo.
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
        $catalogo = Catalogo::vigente();

        if (! $catalogo) {
            return view('paginas.catalogo.tabela', [
                'catalogo' => null,
                'faixas' => [],
                'linhas' => collect(),
                'categoria' => null,
                'visao' => 'venda',
            ]);
        }

        // Filtro invalido na URL vira "tudo", em vez de erro: parametro digitado
        // errado nao deve derrubar a tela.
        $categoria = Categoria::tentar($request->query('categoria'));
        $visao = array_key_exists((string) $request->query('visao'), self::VISOES)
            ? $request->query('visao')
            : 'venda';

        // Categoria suprimida nao abre nem digitando na URL. A aba travada e o
        // aviso; a regra e aqui, senao a trava seria so desenho.
        if ($categoria === null || $categoria->suprimida()) {
            $categoria = Categoria::Credito;
        }

        $precos = $catalogo->precos()->with('servico')->get();

        return view('paginas.catalogo.tabela', [
            'catalogo' => $catalogo,
            'faixas' => Catalogo::faixasDe($precos),
            'linhas' => $this->linhas($precos, $categoria),
            'categoria' => $categoria?->value,
            'visao' => $visao,
        ]);
    }

    /** Pagina propria: parametro mexe no catalogo inteiro e nao na linha. */
    public function parametros()
    {
        $catalogo = Catalogo::vigente();

        abort_if($catalogo === null, 404);

        return view('paginas.catalogo.parametros', [
            'catalogo' => $catalogo,
        ]);
    }

    public function salvarParametros(ParametrosCatalogoRequest $request, Catalogo $catalogo)
    {
        $catalogo->update([
            'imposto_bps' => $request->bps('imposto'),
        ]);

        $catalogo->refresh();

        return back()->with('ok', "Imposto {$catalogo->impostoRotulo()} atualizado.");
    }

    /**
     * Uma linha por servico, com os precos indexados pela faixa.
     *
     * Assim a tabela da tela e so um loop sobre as faixas, e o filtro acontece
     * em memoria sobre o que ja foi carregado, sem voltar ao banco.
     */
    private function linhas(Collection $precos, ?Categoria $categoria): Collection
    {
        return $precos
            ->groupBy('servico_id')
            ->map(fn (Collection $doServico) => [
                'servico' => $doServico->first()->servico,
                'precos' => $doServico->keyBy('consumo_minimo_cents'),
            ])
            ->when($categoria, fn (Collection $linhas) => $linhas->filter(
                fn (array $linha) => $linha['servico']->categoria === $categoria
            ))
            ->sortBy(fn (array $linha) => $linha['servico']->nome)
            ->values();
    }
}
