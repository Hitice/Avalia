<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\Servico;
use App\Support\FiltroConsultas;
use Illuminate\Http\Request;

/**
 * Painel de consultas da administracao: todas as empresas, um lugar so.
 *
 * Responde as perguntas de operacao que a ficha de cada empresa nao responde
 * porque so olha uma por vez: o fornecedor esta respondendo? qual servico esta
 * falhando? o volume caiu esta semana?
 *
 * Tela de leitura. A consulta e registrada pela acao de consumo, nunca a mao:
 * linha criada por aqui seria cobranca sem contrapartida e sem trilha.
 *
 * O vendedor nao entra: a lista dele e /carteira/consultas, restrita as empresas
 * da carteira. Sao telas separadas, e nao a mesma com um `@if` no meio.
 */
class ConsultaController extends Controller
{
    private const POR_PAGINA = 50;

    public function __invoke(Request $pedido)
    {
        $filtradas = FiltroConsultas::aplicar(Consulta::query(), $pedido);

        return view('paginas.consultas.index', [
            'escolha' => FiltroConsultas::escolhido($pedido),
            'resumo' => FiltroConsultas::resumo($filtradas),
            'saude' => $this->saude($pedido),
            'servicos' => Servico::orderBy('nome')->get(),
            'consultas' => $filtradas->with(['servico', 'cliente'])->latest('id')
                ->paginate(self::POR_PAGINA)->withQueryString(),
        ]);
    }

    /**
     * Como o fornecedor esta se comportando no recorte escolhido.
     *
     * Tempo medio so das que deram certo: tentativa que falhou por tempo
     * esgotado tem duracao alta e nao representa a resposta do fornecedor, e
     * misturar as duas faz a media piorar justo quando o servico melhora.
     *
     * @return array{tempo_medio_ms: int|null, por_servico: \Illuminate\Support\Collection}
     */
    private function saude(Request $pedido): array
    {
        $base = fn () => FiltroConsultas::aplicar(Consulta::query(), $pedido);

        $medio = (clone $base())->where('situacao', Consulta::SUCESSO)->avg('duracao_ms');

        $porServico = $base()
            ->selectRaw('servico_id, count(*) as total')
            ->selectRaw('sum(case when situacao = ? then 1 else 0 end) as falhas', [Consulta::FALHA])
            ->groupBy('servico_id')
            ->orderByDesc('falhas')
            ->with('servico')
            ->get();

        return [
            'tempo_medio_ms' => $medio === null ? null : (int) round($medio),
            'por_servico' => $porServico,
        ];
    }
}
