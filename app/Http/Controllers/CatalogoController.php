<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use App\Models\VersaoCatalogo;
use Illuminate\Http\Request;

/**
 * Telas de catalogo. Somente leitura, fora a ativacao de versao.
 *
 * Reajuste de preco nao acontece por formulario nesta fase: a versao ativa e
 * imutavel por regra de negocio, e a proxima nasce de `duplicar()`. Editar
 * preco na tela viria com a expectativa errada de que da para "so corrigir um
 * numerinho" — e nao da, porque contrato e fatura ja apontam para ele.
 */
class CatalogoController extends Controller
{
    public function index()
    {
        $versoes = VersaoCatalogo::query()
            ->withCount(['precos', 'planos'])
            ->orderByDesc('id')
            ->get();

        return view('paginas.catalogo.index', [
            'versoes' => $versoes,
            'vigente' => $versoes->firstWhere('situacao', 'ativa'),
        ]);
    }

    public function versao(Request $request, VersaoCatalogo $versao)
    {
        $categoria = $request->query('categoria');

        if (! array_key_exists($categoria, Servico::CATEGORIAS)) {
            $categoria = null;
        }

        // Uma linha por servico, com os precos indexados pela faixa. Assim a
        // tabela da tela e so um loop sobre as faixas da versao.
        $linhas = $versao->precos()
            ->with('servico')
            ->get()
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

        return view('paginas.catalogo.versao', [
            'versao' => $versao,
            'faixas' => $versao->faixas(),
            'linhas' => $linhas,
            'categoria' => $categoria,
        ]);
    }

    /**
     * Poe a versao em vigor.
     *
     * E o passo que o seeder deliberadamente nao da: importar preco e barato,
     * assumir que ele esta homologado nao e.
     */
    public function ativar(VersaoCatalogo $versao)
    {
        if ($versao->estaCongelada()) {
            return back()->with('erro', "A versao '{$versao->rotulo}' ja foi {$versao->situacao}.");
        }

        if ($versao->precos()->count() === 0) {
            return back()->with('erro', 'Versao sem preco nenhum nao entra em vigor.');
        }

        $versao->ativar();

        return back()->with('ok', "Versao '{$versao->rotulo}' em vigor. A partir de agora ela nao aceita alteracao.");
    }
}
