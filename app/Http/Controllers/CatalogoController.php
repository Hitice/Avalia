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
     * Copia a versao para um rascunho editavel.
     *
     * E o unico caminho para mudar preco depois da ativacao. A versao antiga
     * fica intacta porque contrato e fatura ja emitidos apontam para ela.
     */
    public function duplicar(Request $request, VersaoCatalogo $versao)
    {
        $dados = $request->validate([
            'rotulo' => ['required', 'string', 'max:120', Rule::unique('versoes_catalogo', 'rotulo')],
        ], [], ['rotulo' => 'nome da versao']);

        $nova = $versao->duplicar($dados['rotulo']);

        return redirect()
            ->route('catalogo.versoes.mostrar', $nova)
            ->with('ok', "Rascunho '{$nova->rotulo}' criado com os precos de '{$versao->rotulo}'. Ajuste e ative.");
    }

    /**
     * Grava os precos editados de um rascunho.
     *
     * Escreve em lote: sao ate 301 linhas, e uma consulta por linha contra
     * banco remoto levaria minutos.
     */
    public function precos(Request $request, VersaoCatalogo $versao)
    {
        if ($versao->estaCongelada()) {
            return back()->with('erro', "A versao '{$versao->rotulo}' esta {$versao->situacao} e nao aceita alteracao.");
        }

        $request->validate([
            'precos' => ['array'],
            'precos.*' => ['nullable', 'string', 'max:20'],
        ]);

        $informados = collect($request->input('precos', []))
            ->map(fn ($valor) => Dinheiro::paraCentavos($valor))
            ->filter(fn (?int $centavos) => $centavos !== null && $centavos >= 0);

        // So aceita id que pertence a esta versao: id chutado no formulario
        // nao pode reprecificar outra tabela.
        $linhas = $versao->precos()
            ->whereIn('id', $informados->keys())
            ->get(['id', 'versao_id', 'servico_id', 'consumo_minimo_cents', 'preco_cents'])
            ->filter(fn (Preco $preco) => $preco->preco_cents !== $informados[$preco->id])
            ->map(fn (Preco $preco) => [
                'id' => $preco->id,
                'versao_id' => $preco->versao_id,
                'servico_id' => $preco->servico_id,
                'consumo_minimo_cents' => $preco->consumo_minimo_cents,
                'preco_cents' => $informados[$preco->id],
            ])
            ->values()
            ->all();

        if ($linhas === []) {
            return back()->with('ok', 'Nenhum preco mudou.');
        }

        // upsert nao dispara evento de model, entao a guarda de congelamento
        // do Preco nao roda aqui — o estaCongelada() acima e o que protege.
        Preco::upsert($linhas, ['id'], ['preco_cents']);

        return back()->with('ok', count($linhas).' preco(s) atualizado(s).');
    }

    /**
     * Aplica um percentual sobre os precos do rascunho.
     *
     * Um UPDATE so: reajuste linha a linha seriam 301 idas ao banco.
     */
    public function reajustar(Request $request, VersaoCatalogo $versao)
    {
        if ($versao->estaCongelada()) {
            return back()->with('erro', "A versao '{$versao->rotulo}' esta {$versao->situacao} e nao aceita alteracao.");
        }

        $dados = $request->validate([
            'percentual' => ['required', 'numeric', 'between:-90,900'],
            'categoria' => ['nullable', Rule::in(array_keys(Servico::CATEGORIAS))],
        ]);

        // %.6F garante ponto decimal e nada de notacao cientifica ao entrar na
        // SQL. O valor ja passou por 'numeric' e pela faixa, mas o formato e o
        // que impede uma surpresa de locale virar SQL invalida.
        $fator = sprintf('%.6F', 1 + ((float) $dados['percentual'] / 100));

        $afetados = $versao->precos()
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
