<?php

namespace App\Http\Controllers;

use App\Actions\Negocios\GerarLinkDeAvaliacao;
use App\Enums\SituacaoNegocio;
use App\Exceptions\NaoEncontrado;
use App\Exceptions\Recusa;
use App\Models\Negocio;
use App\Services\Google\BuscarLugar;
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
            ->with('linkAvaliacao:id,codigo,cliques')
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
    /** Definitivo: cadastro de teste ou errado. A placa vendida fica, so perde o vinculo com o negocio. */
    public function excluir(Negocio $negocio)
    {
        \App\Models\Vinculo::where('entidade_tipo', $negocio->getMorphClass())->where('entidade_id', $negocio->id)->delete();
        \App\Support\Auditar::registrar('negocio.excluido', $negocio, ['nome' => $negocio->nome]);
        $negocio->delete();

        return back()->with('ok', 'Negócio '.$negocio->nome.' excluído.');
    }

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
    /*
    |--------------------------------------------------------------------------
    | O link de avaliacao do Google
    |--------------------------------------------------------------------------
    |
    | Pede o nome como ele aparece no perfil, acha o Place ID na Places API,
    | monta o endereco de avaliacao e encurta. Em um passo quando o Google acha
    | um so; com escolha quando acha mais de um, porque nome de loja repete e
    | link errado manda a freguesia avaliar o concorrente.
    */

    public function buscarLugar(Request $pedido, BuscarLugar $buscar, GerarLinkDeAvaliacao $gerar)
    {
        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'max:150'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'negocio_id' => ['nullable', 'integer', 'exists:negocios,id'],
        ]);

        $negocio = isset($dados['negocio_id']) ? Negocio::find($dados['negocio_id']) : null;

        try {
            $lugares = $buscar($dados['nome'], $dados['cidade'] ?? null);
        } catch (NaoEncontrado $nao) {
            // Pede a cidade so agora: na primeira tentativa ela e ruido, e na
            // segunda e o que desempata.
            return back()->with('erro', $nao->getMessage())->with('pedirCidade', true)->withInput();
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage())->withInput();
        }

        // Um resultado nao pede confirmacao: pedir clique para escolher entre uma
        // opcao so e passo que nao decide nada.
        if (count($lugares) === 1) {
            return $this->entregar($gerar, $lugares[0], $negocio);
        }

        return back()->with('lugares', $lugares)->withInput();
    }

    public function gerarLink(Request $pedido, GerarLinkDeAvaliacao $gerar)
    {
        $dados = $pedido->validate([
            'place_id' => ['required', 'string', 'max:255'],
            'nome' => ['nullable', 'string', 'max:150'],
            'negocio_id' => ['nullable', 'integer', 'exists:negocios,id'],
        ]);

        $negocio = isset($dados['negocio_id']) ? Negocio::find($dados['negocio_id']) : null;

        return $this->entregar($gerar, [
            'place_id' => $dados['place_id'],
            'nome' => trim((string) ($dados['nome'] ?? '')) ?: 'Place ID '.substr($dados['place_id'], 0, 10),
        ], $negocio);
    }

    /** @param array{place_id: string, nome: string, endereco?: string} $lugar */
    private function entregar(GerarLinkDeAvaliacao $gerar, array $lugar, ?Negocio $negocio)
    {
        try {
            $link = $gerar($lugar['place_id'], $lugar['nome'], $negocio);
        } catch (Recusa $recusa) {
            return back()->with('erro', $recusa->getMessage())->withInput();
        }

        return back()
            ->with('ok', $lugar['nome'].': link de avaliação pronto.')
            ->with('linkPronto', route('l', ['codigo' => $link->codigo]));
    }
}
