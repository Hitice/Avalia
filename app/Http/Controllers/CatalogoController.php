<?php

namespace App\Http\Controllers;

use App\Actions\Catalogo\AjustarPrecosAoAlvo;
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
                'abaixoDoAlvo' => 0,
                'alvosBps' => [],
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
            // Margem furada nao se descobre lendo coluna por coluna: o numero
            // vem somado, com o caminho para corrigir ao lado.
            'abaixoDoAlvo' => AjustarPrecosAoAlvo::abaixoDoAlvo($catalogo)->count(),
            // A escada de margem fica visivel no cabecalho da visao margem:
            // sem ela, o operador compara cada numero com um alvo que so
            // existe na cabeca de quem definiu.
            'alvosBps' => $catalogo->margemAlvoPorFaixa(Catalogo::faixasDe($precos)),
        ]);
    }

    /**
     * Sobe ao alvo todo preco que rende menos que a margem da propria faixa.
     *
     * A guarda da tela de edicao so recusa preco no prejuizo, e so para quem
     * edita servico a servico. Preco vindo da carga inicial nunca passou por
     * ela, e mudar imposto, comissao, custo ou a propria politica de margem
     * desloca o alvo do catalogo inteiro sem reavaliar linha nenhuma.
     */
    public function ajustarAoAlvo(AjustarPrecosAoAlvo $ajustar)
    {
        $catalogo = Catalogo::vigente();

        if (! $catalogo) {
            return back()->with('erro', 'Não há tabela de preços vigente.');
        }

        $ajustados = $ajustar($catalogo);

        return back()->with($ajustados > 0 ? 'ok' : 'erro', $ajustados > 0
            ? $ajustados.' '.($ajustados === 1 ? 'preço subiu' : 'preços subiram').' para a margem alvo da faixa. O reajuste vale para o consumo daqui em diante.'
            : 'Nenhum preço está abaixo da margem alvo.');
    }

    /** Pagina propria: parametro mexe no catalogo inteiro e nao na linha. */
    public function parametros()
    {
        $catalogo = Catalogo::vigente();

        abort_if($catalogo === null, 404);

        return view('paginas.catalogo.parametros', [
            'catalogo' => $catalogo,
            // A escada em numeros, na hora de decidir: dois campos abstratos
            // viram alvos concretos por faixa.
            'alvos' => $catalogo->margemAlvoPorFaixa(Catalogo::faixasDe($catalogo->precos()->get())),
        ]);
    }

    public function salvarParametros(ParametrosCatalogoRequest $request, Catalogo $catalogo)
    {
        $catalogo->update([
            'imposto_bps' => $request->bps('imposto'),
            'margem_alvo_bps' => $request->bps('margem_alvo'),
            'degrau_margem_bps' => $request->bps('degrau_margem'),
        ]);

        $catalogo->refresh();

        // Salvar parametro nao mexe em preco nenhum, e a mensagem diz isso: o
        // reajuste e outro botao, na tabela, e e decisao a parte.
        return back()->with('ok', sprintf(
            'Imposto %s, margem alvo %s e degrau de %s por faixa. Nenhum preço foi alterado.',
            $catalogo->impostoRotulo(), $catalogo->margemAlvoRotulo(), $catalogo->degrauRotulo(),
        ));
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
