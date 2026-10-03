<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\PagarComissao;
use App\Actions\Financeiro\ContasAPagar;
use App\Actions\Financeiro\PagarComissaoOne;
use App\Actions\Financeiro\Repasses;
use App\Actions\Socios\RegistrarLancamento;
use App\Enums\NaturezaLancamento;
use App\Exceptions\Recusa;
use App\Models\ContaAPagar;
use App\Models\Socio;
use App\Models\Staff;
use App\Support\Dinheiro;
use Illuminate\Http\Request;

/**
 * Pagamentos: tudo que sai do caixa para gente, numa tela, com a lista de
 * Pix. A casa paga na sexta, mas a tela e o sistema de pagamento. Cada botao chama a acao que ja existe; aqui so o fluxo.
 */
class PagamentosController extends Controller
{
    public function index()
    {
        $sales = Repasses::comissoesSales();
        $one = Repasses::comissoesOne();
        $prolabore = Repasses::prolabore(now());
        $contas = Repasses::contasAte(now()->endOfWeek());

        // A lista de Pix: quem recebe, a chave e o valor, na ordem de pagar.
        $pix = collect()
            ->concat($sales->map(fn ($r) => ['nome' => $r['nome'], 'chave' => $r['pix'], 'cents' => $r['cents'], 'motivo' => 'comissão de placas']))
            ->concat($one->where('cents', '>', 0)->map(fn ($r) => ['nome' => $r['nome'], 'chave' => $r['pix'], 'cents' => $r['cents'], 'motivo' => 'comissão de consultas']))
            ->concat($prolabore->where('sugerido', '>', 0)->map(fn ($r) => ['nome' => $r['staff']->nome, 'chave' => $r['staff']->pix_chave, 'cents' => $r['sugerido'], 'motivo' => 'pró-labore']))
            ->values();

        return view('paginas.erp.pagamentos', compact('sales', 'one', 'prolabore', 'contas', 'pix'));
    }

    public function pagarSales(Staff $vendedor, PagarComissao $pagar)
    {
        return $this->tenta(fn () => $pagar($vendedor), fn ($r) => 'Comissão de placas de '.$vendedor->nome.' paga: '.Dinheiro::brl($r['cents']).'.');
    }

    public function pagarOne(Staff $vendedor, PagarComissaoOne $pagar)
    {
        return $this->tenta(fn () => $pagar($vendedor), fn ($r) => 'Comissão de consultas de '.$vendedor->nome.' paga: '.Dinheiro::brl($r['cents']).'.');
    }

    public function prolabore(Request $pedido, Socio $socio, RegistrarLancamento $registrar)
    {
        $dados = $pedido->validate(['valor' => ['required', 'string', 'max:20']]);
        $cents = Dinheiro::paraCentavos($dados['valor']) ?? 0;

        return $this->tenta(fn () => $registrar(NaturezaLancamento::Prolabore, [
            'descricao' => 'Pró-labore de '.$socio->nome.', semana de '.now()->format('d/m'),
            'valor_cents' => $cents,
            'competencia' => now()->format('Y-m'),
            'ocorrido_em' => now()->toDateString(),
            'socio_id' => $socio->id,
            'contraparte' => $socio->staff?->nome ?? $socio->nome,
        ]), fn () => 'Pró-labore de '.$socio->nome.' lançado: '.Dinheiro::brl($cents).'.');
    }

    public function pagarConta(ContaAPagar $conta, ContasAPagar $contas)
    {
        return $this->tenta(fn () => $contas->pagar($conta), fn () => 'Conta paga: '.$conta->descricao.'.');
    }

    private function tenta(callable $acao, callable $mensagem)
    {
        try {
            $resultado = $acao();
        } catch (Recusa $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', $mensagem($resultado));
    }
}
