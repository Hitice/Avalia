<?php

namespace App\Http\Controllers;

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Prospeccao\RegistrarConversaoDoLead;
use App\Http\Requests\EmpresaRequest;
use App\Mail\ConviteDeAcesso;
use App\Models\Adesao;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Lead;
use App\Models\Plano;
use App\Models\Staff;
use App\Support\Auditar;
use App\Support\Comissao;
use App\Support\Convite;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * As empresas contratantes e o consumo delas.
 *
 * A ficha da empresa e o lugar onde o fluxo inteiro se ve de uma vez: plano
 * contratado, consultas do mes em aberto e faturas fechadas. As consultas
 * chegam exclusivamente pelas integrações dos fornecedores.
 *
 * O cadastro e aberto ao vendedor, porque quem fecha a venda e quem tem os
 * dados na mao. Duas coisas ele nao decide:
 *
 *   a carteira, que e sempre a dele, senao cadastrar viraria uma forma de
 *   pegar cliente de outro vendedor;
 *   a situacao, que responde por acesso e cobranca. Marcar a propria empresa
 *   como ativa desfaria um bloqueio por inadimplencia.
 *
 * Ja a lista e a ficha continuam so da administracao: elas mostram custo,
 * imposto e lucro de cada fatura.
 */
class EmpresaController extends Controller
{
    public function index(Request $request)
    {
        $escolha = \App\Support\FiltroClientes::escolhido($request);

        return view('paginas.empresas.index', [
            'empresas' => $this->carteiraFiltrada($request)->get(),
            'escolha' => $escolha,
            'removidas' => $escolha['removidas'],
            'quantidadeRemovidas' => Cliente::onlyTrashed()->count(),
            'vendedores' => Staff::where('papel', 'vendedor')->orderBy('nome')->get(),
            'planos' => \App\Models\Plano::orderBy('consumo_minimo_cents')->get(),
        ]);
    }

    /**
     * A mesma carteira que a tela mostra, numa planilha.
     *
     * Exporta o recorte do filtro, e nao a base inteira: quem clica em exportar
     * acabou de montar um recorte na tela, e receber outra coisa obriga a
     * refazer o filtro no Excel.
     *
     * Fica na trilha porque e dado cadastral de terceiro saindo do sistema em
     * arquivo. Quem exportou e quando responde a pergunta que a LGPD faz.
     */
    public function exportar(Request $request, \App\Actions\Planilha\MontarPlanilhaClientes $montar): StreamedResponse
    {
        $clientes = $this->carteiraFiltrada($request)->get();
        $conteudo = $montar($clientes);

        Auditar::registrar('clientes.exportados', null, ['clientes' => $clientes->count()]);

        return response()->streamDownload(
            fn () => print $conteudo,
            'avalia-clientes-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** Um lugar so decide o recorte, para tela e planilha nunca divergirem. */
    private function carteiraFiltrada(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $consulta = Cliente::with(['plano', 'vendedor'])
            ->when($request->boolean('removidas'), fn ($q) => $q->onlyTrashed());

        return \App\Support\FiltroClientes::aplicar($consulta, $request)->orderBy('razao_social');
    }

    public function criar()
    {
        return view('paginas.empresas.formulario', $this->opcoes(new Cliente(['situacao' => 'ativo'])));
    }

    public function salvar(EmpresaRequest $request)
    {
        $dados = $this->comOsCamposQuePode($request->dados());

        // A conta nasce com uma senha aleatoria que ninguem conhece; o convite
        // por e-mail entrega o link para a propria empresa definir a dela.
        $dados['senha'] = Str::password(40);

        $empresa = Cliente::create($dados);
        $this->gravarAdesao($empresa, $request);
        $lead = $this->fecharLead($empresa, $request);

        $aviso = null;

        try {
            Mail::to($empresa->email)->send(new ConviteDeAcesso(
                $empresa->responsavel_nome ?: $empresa->razao_social,
                Convite::link($empresa, 'empresa'),
                ehEmpresa: true,
            ));
        } catch (\Throwable $e) {
            report($e);
            $aviso = 'O convite por e-mail não pôde ser enviado. Use o botão de redefinição na edição do cadastro.';
        }

        return redirect($lead ? route('carteira.leads') : $this->depoisDeGravar($empresa))
            ->with('ok', "Empresa '{$empresa->razao_social}' cadastrada."
                .($lead ? ' O lead saiu da prospecção.' : '')
                .($aviso ? '' : ' Convite de acesso enviado por e-mail.'))
            ->with('erro', $aviso);
    }

    /**
     * Fecha o lead de origem, quando o cadastro veio de um.
     *
     * O `lead_id` chega escondido no formulario da conversao. E conferido aqui
     * e nao aceito de olhos fechados: para o vendedor, so lead compartilhado com
     * ele; para a administracao, qualquer um. Sem essa conferencia, um POST
     * montado a mao fecharia lead alheio.
     *
     * Roda DEPOIS de a empresa existir. Lead marcado como convertido sem empresa
     * do outro lado sairia da fila de quem prospecta sem que a venda existisse.
     */
    private function fecharLead(Cliente $empresa, EmpresaRequest $request): ?Lead
    {
        $id = (int) $request->input('lead_id');

        if ($id <= 0) {
            return null;
        }

        $leads = Lead::query();

        if (! $this->ehAdmin()) {
            $leads->doVendedor(auth('staff')->id());
        }

        $lead = $leads->whereKey($id)->first();

        if ($lead === null) {
            return null;
        }

        app(RegistrarConversaoDoLead::class)($lead, $empresa);

        return $lead;
    }

    public function editar(Cliente $empresa)
    {
        $this->soDaPropriaCarteira($empresa);

        return view('paginas.empresas.formulario', $this->opcoes($empresa));
    }

    /**
     * Reenvia o convite de redefinicao de senha para o e-mail da empresa.
     *
     * Mesma regra de carteira da edicao: vendedor so reenvia para empresa que
     * e dele. Nao mexe na senha atual.
     */
    public function convite(Cliente $empresa)
    {
        $this->soDaPropriaCarteira($empresa);

        $aviso = null;

        try {
            Mail::to($empresa->email)->send(new ConviteDeAcesso(
                $empresa->responsavel_nome ?: $empresa->razao_social,
                Convite::link($empresa, 'empresa'),
                ehEmpresa: true,
            ));
        } catch (\Throwable $e) {
            report($e);
            $aviso = 'O convite por e-mail não pôde ser enviado. Tente de novo em instantes.';
        }

        return back()
            ->with('ok', $aviso ? null : "Convite de redefinição enviado para {$empresa->email}.")
            ->with('erro', $aviso);
    }

    /*
    |--------------------------------------------------------------------------
    | Operadores da empresa
    |--------------------------------------------------------------------------
    |
    | As pessoas que consultam em nome da empresa, cada uma com conta propria:
    | dez atendentes num login so e "quem consultou?" sem resposta. So a
    | administracao cria e desativa, e a senha nasce aleatoria com convite.
    |
    */

    public function operadorSalvar(Request $pedido, Cliente $empresa)
    {
        $dados = $pedido->validate([
            'nome' => ['required', 'string', 'min:5', 'max:150'],
            // Unico nas tres tabelas: a porta de entrada e uma so, e o mesmo
            // e-mail em duas contas tornaria o login ambiguo.
            'email' => ['required', 'email', 'max:150', 'unique:operadores,email', 'unique:clientes,email', 'unique:staff,email'],
        ], [
            'email.unique' => 'Este e-mail já tem um acesso.',
        ], ['nome' => 'nome', 'email' => 'e-mail']);

        $operador = $empresa->operadores()->create($dados + ['senha' => Str::password(40), 'ativo' => true]);
        Auditar::registrar('operador.criado', $operador, ['cliente_id' => $empresa->id, 'email' => $operador->email]);

        $aviso = $this->enviaConviteDoOperador($operador, $empresa);

        return back()
            ->with('ok', $aviso ? null : "Operador '{$operador->nome}' criado. Convite de acesso enviado por e-mail.")
            ->with('erro', $aviso);
    }

    public function operadorConvite(Cliente $empresa, \App\Models\Operador $operador)
    {
        abort_unless($operador->cliente_id === $empresa->id, 404);

        $aviso = $this->enviaConviteDoOperador($operador, $empresa);

        return back()
            ->with('ok', $aviso ? null : "Convite de redefinição enviado para {$operador->email}.")
            ->with('erro', $aviso);
    }

    public function operadorAlternar(Cliente $empresa, \App\Models\Operador $operador)
    {
        abort_unless($operador->cliente_id === $empresa->id, 404);

        $operador->update(['ativo' => ! $operador->ativo]);

        // Desativar derruba a sessao aberta na hora: acesso desligado que
        // continua operando ate o cookie expirar nao e acesso desligado.
        if (! $operador->ativo) {
            $operador->revogaSessoes();
        }

        Auditar::registrar($operador->ativo ? 'operador.reativado' : 'operador.desativado', $operador, ['cliente_id' => $empresa->id]);

        return back()->with('ok', $operador->ativo
            ? "Acesso de {$operador->nome} reativado."
            : "Acesso de {$operador->nome} desativado.");
    }

    private function enviaConviteDoOperador(\App\Models\Operador $operador, Cliente $empresa): ?string
    {
        try {
            Mail::to($operador->email)->send(new ConviteDeAcesso(
                $operador->nome,
                Convite::link($operador, 'operador'),
                ehEmpresa: true,
                operadorDe: $empresa->razao_social,
            ));

            return null;
        } catch (\Throwable $e) {
            report($e);

            return 'O convite por e-mail não pôde ser enviado. Use o botão de reenvio.';
        }
    }

    public function atualizar(EmpresaRequest $request, Cliente $empresa)
    {
        $this->soDaPropriaCarteira($empresa);

        $situacaoAntes = $empresa->situacao;
        $empresa->update($this->comOsCamposQuePode($request->dados(), $empresa));
        $this->gravarAdesao($empresa, $request);

        // Mudar situacao e decisao operacional (bloqueio, baixa, reativacao):
        // entra na trilha com o antes e o depois, ja em lingua de gente.
        if ($empresa->situacao !== $situacaoAntes) {
            Auditar::registrar('empresa.situacao', $empresa, [
                'de' => \App\Support\Rotulos::empresa($situacaoAntes),
                'para' => \App\Support\Rotulos::empresa($empresa->situacao),
            ]);
        }

        // Situacao que fecha o acesso derruba a sessao aberta na hora, senao a
        // empresa continua consultando ate o cookie expirar.
        if (! $empresa->podeEntrar()) {
            $empresa->revogaSessoes();
        }

        return redirect($this->depoisDeGravar($empresa))
            ->with('ok', 'Cadastro atualizado.');
    }

    /**
     * Tira a empresa de circulacao sem apagar nada.
     *
     * O vendedor remove da carteira dele; a administracao continua vendo em
     * "removidas" e pode restaurar. Nao ha exclusao de verdade: consulta,
     * fatura e trilha apontam para a empresa, e o historico fiscal e dela.
     *
     * Empresa com fatura emitida so a administracao remove. A partir da
     * primeira cobranca ela deixou de ser um cadastro e virou financeiro.
     */
    public function remover(Cliente $empresa)
    {
        $this->soDaPropriaCarteira($empresa);

        if (! $this->ehAdmin() && $empresa->faturas()->exists()) {
            return back()->with('erro', 'Esta empresa já tem fatura emitida. Peça a remoção à administração.');
        }

        $empresa->revogaSessoes();
        $empresa->delete();

        Auditar::registrar('empresa.removida', $empresa, ['razao_social' => $empresa->razao_social]);

        return redirect($this->ehAdmin() ? route('empresas.index') : route('carteira'))
            ->with('ok', "Empresa '{$empresa->razao_social}' removida.");
    }

    /** So a administracao traz de volta. */
    public function restaurar(int $empresa)
    {
        $removida = Cliente::onlyTrashed()->findOrFail($empresa);
        $removida->restore();

        Auditar::registrar('empresa.restaurada', $removida, ['razao_social' => $removida->razao_social]);

        return redirect()
            ->route('empresas.index')
            ->with('ok', "Empresa '{$removida->razao_social}' restaurada.");
    }

    /**
     * Exclusao definitiva, so para empresa ja removida e sem historico.
     *
     * A regra da casa continua: onde ha historico (consulta, fatura, aceite)
     * nao existe exclusao, existe desativacao. O que este botao resolve e o
     * cadastro errado, de teste ou duplicado, que nunca operou e nao precisa
     * viver para sempre na lista de removidas.
     */
    public function excluir(int $empresa)
    {
        $removida = Cliente::onlyTrashed()->findOrFail($empresa);

        $temHistorico = $removida->faturas()->exists()
            || $removida->consultas()->exists()
            || $removida->aceitesDocumentos()->exists();

        if ($temHistorico) {
            return back()->with('erro', 'Esta empresa tem consultas, faturas ou aceites: o histórico é dela e não se apaga. Mantenha como removida.');
        }

        // O rotulo congelado na trilha e o que sobra depois do forceDelete.
        Auditar::registrar('empresa.excluida', $removida, ['razao_social' => $removida->razao_social]);
        $removida->operadores()->forceDelete();
        $removida->forceDelete();

        return redirect()->route('empresas.index', ['removidas' => 1])
            ->with('ok', "Empresa '{$removida->razao_social}' excluída em definitivo.");
    }

    public function ficha(Cliente $empresa)
    {
        $competencia = Consulta::competenciaDe();

        return view('paginas.empresas.ficha', [
            'empresa' => $empresa->load(['plano.catalogo', 'vendedor', 'adesao']),
            'competencia' => $competencia,
            'consumo' => $empresa->consultas()->where('competencia', $competencia)->sum('preco_cents'),
            'quantidade' => $empresa->consultas()->where('competencia', $competencia)->count(),
            'faturas' => $empresa->faturas()->orderByDesc('competencia')->get(),
        ]);
    }

    public function fechar(Cliente $empresa, FecharCompetencia $fechar)
    {
        $resultado = $fechar($empresa, Consulta::competenciaDe());

        if ($resultado['erro']) {
            return back()->with('erro', $resultado['erro']);
        }

        return back()->with('ok', sprintf(
            'Competencia fechada: fatura de %s, vencimento em %s.',
            $resultado['fatura']->totalRotulo(),
            $resultado['fatura']->vencimento()->format('d/m/Y'),
        ));
    }

    /** A ficha e da administracao; o vendedor volta para a carteira. */
    private function depoisDeGravar(Cliente $empresa): string
    {
        return $this->ehAdmin() ? route('empresas.ficha', $empresa) : route('carteira');
    }

    private function ehAdmin(): bool
    {
        return (bool) auth('staff')->user()?->ehAdmin();
    }

    /** Vendedor so abre empresa da carteira dele. */
    private function soDaPropriaCarteira(Cliente $empresa): void
    {
        abort_if(
            ! $this->ehAdmin() && $empresa->vendedor_id !== auth('staff')->id(),
            403,
        );
    }

    /**
     * Tira do que veio da tela o que o vendedor nao decide.
     *
     * A validacao ja passou; isto nao e sobre formato e sim sobre autoridade.
     * Vendedor que forje vendedor_id ou situacao no POST tem os dois campos
     * ignorados aqui, e nao recusados: o cadastro dele continua valido.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function comOsCamposQuePode(array $dados, ?Cliente $empresa = null): array
    {
        if ($this->ehAdmin()) {
            return $dados;
        }

        $dados['vendedor_id'] = auth('staff')->id();
        $dados['situacao'] = $empresa?->situacao ?? 'ativo';

        return $dados;
    }

    /** @return array<string, mixed> */
    private function opcoes(Cliente $empresa): array
    {
        return [
            'empresa' => $empresa,
            'planos' => Plano::where('ativo', true)->orderBy('consumo_minimo_cents')->get(),
            // So quem tem carteira entra na lista: administrador nao tem
            // comissao nem carteira (PDD, secao 3), e aparecer aqui convidava
            // ao vinculo errado.
            'vendedores' => Staff::where('ativo', true)->where('papel', 'vendedor')->orderBy('nome')->get(),
        ];
    }

    private function gravarAdesao(Cliente $empresa, EmpresaRequest $request): void
    {
        if (! $this->ehAdmin()) {
            return;
        }

        $valor = Dinheiro::paraCentavos($request->input('adesao_valor')) ?? 0;
        $parcelas = (int) ($request->input('adesao_parcelas') ?: 1);
        $adesao = $empresa->adesao;

        if ($valor === 0 && ! $adesao) {
            return;
        }

        // A parcela gravada e a primeira, que carrega a sobra da divisao: e ela
        // que o cliente confere na assinatura. Adesao::parcelasDe garante que a
        // soma das cobrancas bata com o total combinado.
        $parcela = Adesao::parcelasDe($valor, $parcelas)[0] ?? 0;
        $vendedor = Comissao::parteAdesaoCents($valor);
        Adesao::updateOrCreate(['cliente_id' => $empresa->id], [
            'valor_cents' => $valor,
            'parcelas' => $parcelas,
            'valor_parcela_cents' => $parcela,
            'vendedor_cents' => $vendedor,
            'avalia_cents' => $valor - $vendedor,
        ]);

        Auditar::registrar('adesao.atualizada', $empresa, ['valor_cents' => $valor, 'parcelas' => $parcelas]);
    }
}
