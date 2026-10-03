<?php

namespace App\Http\Controllers;

use App\Actions\Financeiro\ContasAPagar;
use App\Exceptions\Recusa;
use App\Models\ContaAPagar;
use App\Models\ContaFinanceira;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ContasAPagarController extends Controller
{
    public function index()
    {
        return view('paginas.gestao.contas', [
            'abertas' => ContaAPagar::emAberto()->with('categoria')->orderBy('vence_em')->get(),
            'pagas' => ContaAPagar::whereNotNull('pago_em')->with('categoria')->orderByDesc('pago_em')->limit(30)->get(),
            'categorias' => ContaFinanceira::where('grupo', 'despesa')->where('ativa', true)->orderBy('nome')->get(),
        ]);
    }

    public function salvar(Request $pedido, ContasAPagar $contas)
    {
        $dados = $pedido->validate([
            'descricao' => ['required', 'string', 'max:200'],
            'fornecedor' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['required', 'integer', 'exists:contas_financeiras,id'],
            'valor' => ['required', 'string', 'max:20'],
            'vence_em' => ['required', 'date'],
        ]);

        try {
            $conta = $contas->provisionar([
                'descricao' => $dados['descricao'],
                'fornecedor' => $dados['fornecedor'] ?? null,
                'categoria_id' => (int) $dados['categoria_id'],
                'valor_cents' => Dinheiro::paraCentavos($dados['valor']) ?? 0,
                'vence_em' => Carbon::parse($dados['vence_em'])->toDateString(),
            ]);
        } catch (Recusa $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Conta registrada: '.$conta->descricao.', '.Dinheiro::brl($conta->valor_cents).' até '.$conta->vence_em->format('d/m').'.');
    }

    public function pagar(ContaAPagar $conta, ContasAPagar $contas)
    {
        try {
            $contas->pagar($conta);
        } catch (Recusa $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Conta paga: '.$conta->descricao.'.');
    }
}
