<?php

namespace App\Http\Controllers;

use App\Actions\Consumo\ExecutarConsulta;
use App\Actions\Documentos\RegistrarAceiteDocumento;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Servico;
use Illuminate\Http\Request;

/** Painel restrito à própria empresa contratante. */
class AreaClienteController extends Controller
{
    public function painel()
    {
        $empresa = auth('empresa')->user();
        $documentos = DocumentoLegal::query()->where('ativo', true)->orderBy('titulo')->get();
        $aceites = $empresa->aceitesDocumentos()->pluck('documento_id')->all();
        $competencia = Consulta::competenciaDe();
        $plano = $empresa->plano?->load('franquias.servico');
        $uso = $empresa->consultas()
            ->where('competencia', $competencia)
            ->selectRaw('servico_id, count(*) as quantidade')
            ->groupBy('servico_id')
            ->pluck('quantidade', 'servico_id');
        $faturas = $empresa->faturas()->with('cobrancaAsaas')->orderByDesc('competencia')->limit(6)->get();

        // O que ela pode consultar hoje: precificado na faixa dela e liberado.
        $servicos = $plano?->servicosDisponiveis() ?? collect();
        $ultimas = $empresa->consultas()->with('servico')->latest('id')->limit(10)->get();

        return view('paginas.empresa.painel', compact(
            'empresa', 'documentos', 'aceites', 'competencia', 'plano', 'uso', 'faturas',
            'servicos', 'ultimas',
        ));
    }

    /**
     * A consulta em si, pedida pela empresa.
     *
     * A finalidade e obrigatoria e vai gravada junto: a secao 14 do PDD exige
     * que cada consulta tenha motivo e responsavel rastreaveis, e e o que
     * sustenta a base legal se alguem perguntar depois por que aquele CPF foi
     * consultado.
     */
    public function consultar(Request $request, ExecutarConsulta $consultar)
    {
        $empresa = auth('empresa')->user();

        $dados = $request->validate([
            'servico_id' => ['required', 'integer'],
            'documento' => ['required', 'string', 'min:11', 'max:20'],
            'finalidade' => ['required', 'string', 'min:10', 'max:120'],
            'solicitante' => ['nullable', 'string', 'max:150'],
        ], [
            'documento.min' => 'Informe um CPF ou CNPJ completo.',
            'finalidade.required' => 'Diga para que a consulta sera usada.',
            'finalidade.min' => 'Descreva a finalidade com mais detalhe.',
        ]);

        $servico = Servico::findOrFail($dados['servico_id']);

        $resultado = $consultar(
            $empresa, $servico, $dados['documento'], $dados['finalidade'], $dados['solicitante'] ?? null,
        );

        if ($resultado['erro']) {
            return back()->withInput()->with('erro', $resultado['erro']);
        }

        return redirect()
            ->route('empresa.consultas.ver', $resultado['consulta'])
            ->with('ok', 'Consulta concluída.');
    }

    /** O resultado de uma consulta, so para quem a pediu. */
    public function verConsulta(\App\Models\Consulta $consulta)
    {
        abort_unless($consulta->cliente_id === auth('empresa')->id(), 403);

        return view('paginas.empresa.consulta', ['consulta' => $consulta->load('servico')]);
    }

    public function aceitar(DocumentoLegal $documento, RegistrarAceiteDocumento $registrar)
    {
        $registrar(auth('empresa')->user(), $documento);

        return back()->with('ok', 'Aceite registrado.');
    }
}
