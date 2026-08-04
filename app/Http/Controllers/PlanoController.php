<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanoRequest;
use App\Models\Plano;
use App\Models\VersaoCatalogo;
use Illuminate\Http\Request;

/**
 * Cadastro de planos e da franquia de cada servico.
 *
 * Plano nao e catalogo: ele aponta para uma versao do catalogo e escolhe uma
 * faixa dela. Por isso da para editar um plano a vontade sem violar o
 * congelamento — o que nao muda e a tabela de precos para a qual ele aponta.
 */
class PlanoController extends Controller
{
    public function index()
    {
        $planos = Plano::with('versao')->orderBy('nome')->get();

        return view('paginas.catalogo.planos', ['planos' => $planos]);
    }

    public function criar()
    {
        return view('paginas.catalogo.plano-formulario', [
            'plano' => new Plano(['mensalidade_cents' => 7_990, 'ativo' => true]),
            'versoes' => $this->versoesUsaveis(),
        ]);
    }

    public function salvar(PlanoRequest $request)
    {
        $plano = Plano::create($request->validated());

        return redirect()
            ->route('catalogo.planos.editar', $plano)
            ->with('ok', "Plano '{$plano->nome}' criado. Defina a franquia de cada servico.");
    }

    public function editar(Plano $plano)
    {
        return view('paginas.catalogo.plano-formulario', [
            'plano' => $plano,
            'versoes' => $this->versoesUsaveis(),
            'servicos' => $plano->servicosDisponiveis(),
            'franquias' => $plano->franquias()->pluck('quantidade', 'servico_id'),
            // Preco da faixa do plano, numa consulta so em vez de uma por linha.
            'precos' => $plano->versao->precos()
                ->where('consumo_minimo_cents', $plano->consumo_minimo_cents)
                ->pluck('preco_cents', 'servico_id'),
        ]);
    }

    public function atualizar(PlanoRequest $request, Plano $plano)
    {
        $plano->update($request->validated());

        return redirect()
            ->route('catalogo.planos.editar', $plano)
            ->with('ok', 'Plano atualizado.');
    }

    /**
     * Grava a quantidade incluida por servico.
     *
     * Quantidade zero apaga a linha em vez de gravar zero: ausencia e zero
     * significam a mesma coisa para o faturamento (nenhuma consulta gratis), e
     * manter as duas representacoes so criaria duvida na hora de apurar.
     */
    public function franquias(Request $request, Plano $plano)
    {
        $dados = $request->validate([
            'franquias' => ['array'],
            'franquias.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $permitidos = $plano->servicosDisponiveis()->pluck('id');

        foreach ($permitidos as $servicoId) {
            $quantidade = (int) ($dados['franquias'][$servicoId] ?? 0);

            if ($quantidade === 0) {
                $plano->franquias()->where('servico_id', $servicoId)->delete();

                continue;
            }

            $plano->franquias()->updateOrCreate(
                ['servico_id' => $servicoId],
                ['quantidade' => $quantidade],
            );
        }

        return back()->with('ok', 'Franquia atualizada.');
    }

    /**
     * Versoes que podem receber plano novo.
     *
     * Rascunho entra na lista: e normal montar o plano junto com a tabela,
     * antes de ativar as duas coisas. Encerrada fica de fora — plano novo
     * apontando para tabela fora de vigencia seria erro de cadastro.
     */
    private function versoesUsaveis()
    {
        return VersaoCatalogo::whereIn('situacao', ['rascunho', 'agendada', 'ativa'])
            ->orderByDesc('id')
            ->get();
    }
}
