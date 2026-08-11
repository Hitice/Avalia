<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\Servico;
use App\Support\Auditar;
use App\Support\ConsultaPdf;
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

    public function index(Request $pedido)
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
     * O resultado de uma consulta, para a administracao.
     *
     * A lista nunca mostra o documento consultado, e este metodo e a razao: o
     * acesso a dado pessoal e um EVENTO, e evento se registra. Quem abriu, de
     * qual empresa e quando fica na trilha, e e essa trilha que responde a
     * pergunta que o titular tem o direito de fazer.
     *
     * Consulta expurgada nao abre: o conteudo ja nao existe, e o registro dela
     * continua na lista.
     */
    public function ver(Consulta $consulta)
    {
        abort_unless($consulta->deuCerto() && ! $consulta->expurgada(), 404);

        Auditar::registrar('consulta.aberta', $consulta, [
            'cliente_id' => $consulta->cliente_id,
            'servico' => $consulta->servico?->codigo,
        ]);

        return view('paginas.consultas.ver', ['consulta' => $consulta->load('servico', 'cliente')]);
    }

    /** O mesmo laudo que o cliente baixa, para o atendimento. */
    public function pdf(Consulta $consulta)
    {
        abort_unless($consulta->deuCerto() && ! $consulta->expurgada(), 404);

        Auditar::registrar('consulta.laudo_emitido', $consulta, [
            'cliente_id' => $consulta->cliente_id,
            'servico' => $consulta->servico?->codigo,
        ]);

        $emissor = auth('staff')->user()?->nome;

        return response(ConsultaPdf::resultado($consulta->load('servico'), $emissor), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="consulta-'.($consulta->referencia_externa ?? $consulta->id).'.pdf"',
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
