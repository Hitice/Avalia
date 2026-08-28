<?php

namespace App\Http\Controllers;

use App\Actions\Planilha\MontarPlanilhaLeads;
use App\Actions\Prospeccao\CompartilharLeads;
use App\Actions\Prospeccao\RecolherLeads;
use App\Enums\SituacaoLead;
use App\Http\Requests\LeadRequest;
use App\Models\Lead;
use App\Models\Staff;
use App\Support\Auditar;
use App\Support\FiltroLeads;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A base de prospeccao e a distribuicao dela.
 *
 * Modulo da administracao inteiro, e nao um filtro da carteira: a base tem mais
 * de mil empresas, e quem decide qual vendedor trabalha qual pedaco e a
 * administracao (PDD.md: lead ainda nao tem carteira, e distribui-lo e decisao
 * da administracao). O vendedor ve em /carteira/leads apenas o que recebeu.
 *
 * O filtro e o coracao da tela, e nao um acessorio de busca: e com ele que se
 * monta o recorte ("Uberlandia, com telefone, ainda sem vendedor") que vai ser
 * passado a alguem. Por isso a acao em lote sabe agir sobre o recorte inteiro,
 * e nao apenas sobre as linhas marcadas na pagina: uma base de mil leads
 * distribuida de cinquenta em cinquenta seria distribuida errado.
 */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        $escolha = FiltroLeads::escolhido($request);
        $recorte = $this->recorte($request);

        return view('paginas.leads.index', [
            'leads' => $this->daTela($request),
            'escolha' => $escolha,
            'removidos' => $escolha['removidos'],
            'quantidadeRemovidos' => Lead::onlyTrashed()->count(),

            // Os numeros do recorte, e nao da base: quem esta montando uma
            // distribuicao precisa saber quantos leads ela alcanca e quantos
            // deles tem por onde ligar.
            'noRecorte' => (clone $recorte)->count(),
            'comTelefone' => (clone $recorte)->whereNotNull('telefone')->count(),
            'comEmail' => (clone $recorte)->whereNotNull('email')->count(),
            'semVendedor' => (clone $recorte)->whereDoesntHave('vendedores')->count(),
            'naBase' => Lead::count(),
            'teto' => self::TETO_DA_TELA,

            'vendedores' => Staff::where('papel', 'vendedor')->orderBy('nome')->get(),
            'ufs' => Lead::query()->whereNotNull('uf')->distinct()->orderBy('uf')->pluck('uf'),
            'cidades' => Lead::query()->whereNotNull('cidade')->distinct()->orderBy('cidade')->pluck('cidade'),
            'origens' => Lead::query()->whereNotNull('origem')->distinct()->pluck('origem')
                ->sortBy(fn (string $origem) => (int) $origem)->values(),
        ]);
    }

    /**
     * O recorte do filtro, sem paginacao.
     *
     * @return Builder<Lead>
     */
    private function recorte(Request $request): Builder
    {
        $leads = FiltroLeads::escolhido($request)['removidos']
            ? Lead::onlyTrashed()
            : Lead::query();

        return FiltroLeads::aplicar($leads, $request);
    }

    /**
     * Teto de exibicao da tabela.
     *
     * Nao e paginacao: e um teto, e a tela diz quando bateu nele. A base tem mil
     * linhas, e mil linhas renderizadas somam cinco megabytes de HTML, que o
     * servidor entrega em 200ms e o navegador do celular engasga para montar.
     * Trezentas enchem a rolagem de sobra.
     *
     * Vale so para o que a tela desenha. A acao em lote sobre o filtro alcanca o
     * recorte inteiro, porque o servidor o recalcula a partir do endereco em vez
     * de confiar no que o navegador mandou: se dependesse da lista desenhada, o
     * teto viraria distribuicao incompleta em silencio.
     */
    private const TETO_DA_TELA = 300;

    /**
     * O que a tabela desenha: o recorte ate o teto.
     *
     * Sem paginacao, como as outras tabelas longas do sistema: rola dentro do
     * cartao com o cabecalho fixo, e quem procura um lead usa o filtro.
     *
     * @return Collection<int, Lead>
     */
    private function daTela(Request $request): Collection
    {
        return $this->recorte($request)
            ->with(['vendedores', 'cliente'])
            ->orderBy('nome')
            ->limit(self::TETO_DA_TELA)
            ->get();
    }

    /**
     * Tira o lead de circulacao, ou devolve, num clique.
     *
     * Lead bloqueado nao entra em distribuicao. E decisao da administracao (a
     * empresa pediu para nao ser procurada, ja e cliente por outro caminho, o
     * cadastro esta furado), e por isso nao esta na tela do vendedor.
     *
     * Liberar devolve para "novo", e nao para o estagio anterior: o que valia
     * antes do bloqueio nao vale mais depois dele, e guardar o estagio antigo
     * para restaurar seria inventar um historico que ninguem pediu.
     */
    public function alternar(Lead $lead)
    {
        // Lead que virou cliente nao volta para a fila por um clique: quem
        // desfaz conversao e o cadastro da empresa, do outro lado.
        if ($lead->jaEhCliente()) {
            return back()->with('erro', "{$lead->nome} já é cliente.");
        }

        $bloqueando = $lead->situacao !== SituacaoLead::Bloqueado;

        $lead->update(['situacao' => $bloqueando ? SituacaoLead::Bloqueado : SituacaoLead::Novo]);

        Auditar::registrar($bloqueando ? 'lead.bloqueado' : 'lead.liberado', $lead);

        return back()->with('ok', $bloqueando
            ? "{$lead->nome} fora da prospecção."
            : "{$lead->nome} de volta à prospecção.");
    }

    /**
     * A mesma base que a tela mostra, numa planilha.
     *
     * Fica na trilha porque e cadastro de terceiro saindo do sistema em
     * arquivo, igual a exportacao de clientes: arquivo circula, e quem exportou
     * e quando e a pergunta que a LGPD faz.
     */
    public function exportar(Request $request, MontarPlanilhaLeads $montar): StreamedResponse
    {
        $leads = $this->recorte($request)->with('vendedores')->orderBy('nome')->get();
        $conteudo = $montar($leads);

        Auditar::registrar('leads.exportados', null, ['leads' => $leads->count()]);

        return response()->streamDownload(
            fn () => print $conteudo,
            'avalia-leads-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Compartilha, recolhe ou remove o recorte de uma vez.
     *
     * `escopo` decide sobre o que a acao age: as linhas marcadas na pagina, ou
     * o recorte inteiro do filtro. O segundo existe porque a base tem mais de
     * mil leads e a tela mostra cinquenta: sem ele, "passar Uberlandia para a
     * Ana" viraria vinte e um cliques em vinte e uma paginas.
     */
    public function lote(Request $request, CompartilharLeads $compartilhar, RecolherLeads $recolher)
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:compartilhar,recolher,remover'],
            'escopo' => ['required', 'in:marcados,filtro'],
            'leads' => ['array'],
            'leads.*' => ['integer'],
            // O vendedor e obrigatorio nas duas acoes que mexem em quem ve o
            // que: compartilhar sem destino nao quer dizer nada.
            'vendedor' => ['required_if:acao,compartilhar,recolher', 'nullable', 'integer'],
        ], [
            'vendedor.required_if' => 'Escolha o vendedor.',
        ]);

        $ids = $this->alvos($request, $dados);

        if ($ids === []) {
            return back()->with('erro', 'Nenhum lead selecionado.');
        }

        if ($dados['acao'] === 'remover') {
            return $this->removerLote($ids);
        }

        $vendedor = Staff::find((int) $dados['vendedor']);

        if (! $vendedor || $vendedor->papel !== 'vendedor') {
            return back()->with('erro', 'Escolha um vendedor da equipe.');
        }

        if ($dados['acao'] === 'recolher') {
            $desfeitos = $recolher($ids, $vendedor);

            return back()->with($desfeitos > 0 ? 'ok' : 'erro', $desfeitos > 0
                ? $this->contagem($desfeitos).' fora da lista de '.$vendedor->nome.'.'
                : 'Nenhum desses leads estava com '.$vendedor->nome.'.');
        }

        $novos = $compartilhar($ids, $vendedor);

        return back()->with($novos > 0 ? 'ok' : 'erro', $novos > 0
            ? $this->contagem($novos).' compartilhado'.($novos === 1 ? '' : 's').' com '.$vendedor->nome.'.'
            : $vendedor->nome.' já tinha todos os leads selecionados.');
    }

    /**
     * Os ids sobre os quais o lote age.
     *
     * Com escopo `filtro` eles saem da consulta, e nao do formulario: o recorte
     * vive na barra de endereços, entao o servidor recalcula exatamente o que a
     * tela mostrava em vez de confiar numa lista de mil ids vinda do navegador.
     *
     * @param  array<string, mixed>  $dados
     * @return list<int>
     */
    private function alvos(Request $request, array $dados): array
    {
        if ($dados['escopo'] === 'filtro') {
            return $this->recorte($request)->pluck('id')->all();
        }

        return array_values(array_map('intval', $dados['leads'] ?? []));
    }

    /** @param  list<int>  $ids */
    private function removerLote(array $ids)
    {
        // O escopo padrao ja deixa de fora o que ja estava removido, entao a
        // contagem que volta e de remocao que aconteceu de verdade.
        $removidos = Lead::whereIn('id', $ids)->delete();

        if ($removidos === 0) {
            return back()->with('erro', 'Nenhum lead removido.');
        }

        Auditar::registrar('leads.removidos', null, ['leads' => $removidos]);

        return back()->with('ok', $this->contagem($removidos).' removido'.($removidos === 1 ? '' : 's').' da base.');
    }

    private function contagem(int $quantidade): string
    {
        return $quantidade.' '.($quantidade === 1 ? 'lead' : 'leads');
    }

    public function criar()
    {
        return view('paginas.leads.formulario', [
            'lead' => new Lead(['situacao' => SituacaoLead::Novo]),
            'vendedores' => $this->vendedores(),
        ]);
    }

    public function salvar(LeadRequest $request)
    {
        $lead = Lead::create($request->dados());

        Auditar::registrar('lead.criado', $lead, ['origem' => $lead->origem]);

        return redirect()->route('leads.index')->with('ok', "{$lead->nome} entrou na base.");
    }

    public function editar(Lead $lead)
    {
        return view('paginas.leads.formulario', [
            'lead' => $lead->load('vendedores', 'cliente'),
            'vendedores' => $this->vendedores(),
        ]);
    }

    public function atualizar(LeadRequest $request, Lead $lead)
    {
        $lead->update($request->dados());

        Auditar::registrar('lead.alterado', $lead);

        return redirect()->route('leads.index')->with('ok', "Cadastro de {$lead->nome} atualizado.");
    }

    /**
     * Tira o lead do trabalho, sem apagar: o rastro de com quem ele ja esteve
     * continua de pe, e a decisao de descartar se desfaz num clique.
     */
    public function remover(Lead $lead)
    {
        $lead->delete();

        Auditar::registrar('lead.removido', $lead);

        return back()->with('ok', "{$lead->nome} saiu da base.");
    }

    public function restaurar(int $id)
    {
        $lead = Lead::withTrashed()->findOrFail($id);
        $lead->restore();

        Auditar::registrar('lead.restaurado', $lead);

        return back()->with('ok', "{$lead->nome} de volta a base.");
    }

    /** @return Collection<int, Staff> */
    private function vendedores(): Collection
    {
        return Staff::where('papel', 'vendedor')->orderBy('nome')->get();
    }
}
