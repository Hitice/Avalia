<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoNegocio;
use App\Models\Negocio;
use App\Support\Auditar;
use Illuminate\Http\Request;

/**
 * A base de negocios da frente de marketing.
 *
 * Uma tela de lista, porque a pergunta que se faz aqui e sempre "quem esta
 * esperando a gente": o filtro por situacao mora na barra de endereco, entao a
 * tela vira link e o recorte se compartilha.
 */
class NegociosController extends Controller
{
    public function index(Request $pedido)
    {
        $situacao = SituacaoNegocio::tentar($pedido->query('situacao'));
        $busca = trim((string) $pedido->query('busca', ''));

        $negocios = Negocio::query()
            ->when($situacao, fn ($q) => $q->where('situacao', $situacao->value))
            ->when($busca !== '', fn ($q) => $q->where(fn ($b) => $b
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('responsavel', 'like', "%{$busca}%")
                ->orWhere('cidade', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")))
            ->withCount('etiquetas')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('paginas.negocios.index', [
            'negocios' => $negocios,
            'situacoes' => SituacaoNegocio::rotulos(),
            'filtros' => ['situacao' => $situacao?->value ?? '', 'busca' => $busca],
            'porSituacao' => Negocio::selectRaw('situacao, count(*) as total')
                ->groupBy('situacao')->pluck('total', 'situacao'),
        ]);
    }

    /** Situacao e observacao, que e tudo o que a casa mexe depois do cadastro. */
    public function atualizar(Request $pedido, Negocio $negocio)
    {
        $dados = $pedido->validate([
            'situacao' => ['required', 'string'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);

        $situacao = SituacaoNegocio::tentar($dados['situacao']);

        if ($situacao === null) {
            return back()->with('erro', 'Situação desconhecida.');
        }

        $negocio->update([
            'situacao' => $situacao->value,
            'observacao' => $dados['observacao'] ?? $negocio->observacao,

            // A data entra quando ele chega em Publicado, e sai se voltar atras:
            // data de publicacao em negocio que nao esta publicado e numero que
            // engana na conferencia do mes.
            'cadastrado_no_google_em' => $situacao === SituacaoNegocio::Publicado
                ? ($negocio->cadastrado_no_google_em ?? now())
                : null,
        ]);

        Auditar::registrar('negocio.situacao', $negocio, ['situacao' => $situacao->value]);

        return back()->with('ok', $negocio->nome.': '.$situacao->rotulo().'.');
    }
}
