<?php

namespace App\Http\Controllers;

use App\Actions\Consumo\FecharCompetencia;
use App\Http\Requests\EmpresaRequest;
use App\Models\Adesao;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Plano;
use App\Models\Staff;
use App\Support\Auditar;
use App\Support\Comissao;
use App\Support\Dinheiro;
use Illuminate\Http\Request;

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
        $removidas = $request->boolean('removidas');

        return view('paginas.empresas.index', [
            'empresas' => Cliente::with(['plano', 'vendedor'])
                ->when($removidas, fn ($q) => $q->onlyTrashed())
                ->orderBy('razao_social')
                ->get(),
            'removidas' => $removidas,
            'quantidadeRemovidas' => Cliente::onlyTrashed()->count(),
        ]);
    }

    public function criar()
    {
        return view('paginas.empresas.formulario', $this->opcoes(new Cliente(['situacao' => 'ativo'])));
    }

    public function salvar(EmpresaRequest $request)
    {
        $empresa = Cliente::create($this->comOsCamposQuePode($request->dados()));
        $this->gravarAdesao($empresa, $request);

        return redirect($this->depoisDeGravar($empresa))
            ->with('ok', "Empresa '{$empresa->razao_social}' cadastrada.");
    }

    public function editar(Cliente $empresa)
    {
        $this->soDaPropriaCarteira($empresa);

        return view('paginas.empresas.formulario', $this->opcoes($empresa));
    }

    public function atualizar(EmpresaRequest $request, Cliente $empresa)
    {
        $this->soDaPropriaCarteira($empresa);

        $empresa->update($this->comOsCamposQuePode($request->dados(), $empresa));
        $this->gravarAdesao($empresa, $request);

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
            'vendedores' => Staff::where('ativo', true)->orderBy('nome')->get(),
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

        $parcela = intdiv($valor, $parcelas);
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
