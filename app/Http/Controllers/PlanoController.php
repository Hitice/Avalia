<?php

namespace App\Http\Controllers;

use App\Actions\Catalogo\GravarFranquias;
use App\Http\Requests\FranquiaRequest;
use App\Http\Requests\PlanoRequest;
use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Preco;

/**
 * Cadastro de planos e da franquia de cada servico.
 *
 * O plano escolhe uma faixa do catalogo: e ela que define a coluna de precos
 * que o cliente paga e a aliquota de comissao do vendedor.
 */
class PlanoController extends Controller
{
    public function index()
    {
        // Ordem comercial, nao alfabetica: a grade e uma escada de faixas, e
        // "R$ 1.500,00" viria antes de "R$ 200,00" se ordenasse por nome.
        $planos = Plano::query()
            ->orderBy('consumo_minimo_cents')
            ->orderBy('nome')
            ->get();

        // Faixas que o catalogo oferece, numa consulta so. Perguntar
        // faixaValida() linha a linha custaria uma ida ao banco por plano.
        $faixas = Preco::query()
            ->whereIn('catalogo_id', $planos->pluck('catalogo_id')->unique())
            ->distinct()
            ->pluck('consumo_minimo_cents')
            ->map(fn ($faixa) => (int) $faixa)
            ->all();

        return view('paginas.catalogo.planos', [
            'planos' => $planos,
            'faixaValida' => $planos->mapWithKeys(fn (Plano $plano) => [
                $plano->id => in_array($plano->consumo_minimo_cents, $faixas, true),
            ]),
        ]);
    }

    public function criar()
    {
        return view('paginas.catalogo.plano-formulario', [
            'plano' => new Plano(['mensalidade_cents' => 7_990, 'ativo' => true]),
            'faixas' => $this->faixas(),
        ]);
    }

    public function salvar(PlanoRequest $request)
    {
        $plano = Plano::create($request->validated() + [
            'catalogo_id' => Catalogo::vigente()?->id,
        ]);

        return redirect()
            ->route('catalogo.planos.editar', $plano)
            ->with('ok', "Plano '{$plano->nome}' criado. Defina a franquia de cada servico.");
    }

    public function editar(Plano $plano)
    {
        return view('paginas.catalogo.plano-formulario', [
            'plano' => $plano,
            'faixas' => $this->faixas(),
            'servicos' => $plano->servicosDisponiveis(),
            'franquias' => $plano->franquias()->pluck('quantidade', 'servico_id'),
            // Preco da faixa do plano, numa consulta so em vez de uma por linha.
            'precos' => $plano->catalogo->precos()
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

    public function franquias(FranquiaRequest $request, Plano $plano, GravarFranquias $gravar)
    {
        $gravadas = $gravar($plano, $request->quantidades());

        return back()->with('ok', $gravadas === 0
            ? 'Nenhum servico com franquia neste plano.'
            : "Franquia gravada em {$gravadas} servico(s).");
    }

    /** Faixas do catalogo, prontas para o select do formulario. */
    private function faixas(): array
    {
        return Catalogo::vigente()?->faixas() ?? [];
    }
}
