<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Staff;
use App\Support\Alertas;
use Illuminate\Support\Facades\Auth;

/**
 * A porta de entrada, uma por trabalho.
 *
 * Administrador e vendedor abrem a mesma URL e veem telas diferentes, porque
 * fazem trabalhos diferentes. O painel da administracao responde "a operacao
 * esta saudavel?"; o do vendedor responde "quem eu preciso ligar hoje?".
 *
 * Ate aqui era um painel so com seis numeros iguais, mudando apenas o filtro. O
 * efeito era o previsto na secao 12 do PDD: cada um aprendia a ignorar metade da
 * tela. Pior, o vendedor via "a receber" e "em atraso", que sao dinheiro da
 * Avalia e nao dele, e o administrador via "comissao liberada", que ele nao
 * recebe.
 *
 * A escolha do painel acontece uma vez, aqui. Cada papel tem a sua view inteira,
 * e nao a mesma com condicional em cada campo: e a mesma separacao fisica da
 * secao 3, aplicada a tela que todo mundo abre primeiro.
 */
class PainelController extends Controller
{
    public function __invoke()
    {
        $staff = Auth::guard('staff')->user();

        return $staff->ehAdmin() ? $this->administracao($staff) : $this->vendedor($staff);
    }

    /** A operacao esta saudavel? Quanto entra, quanto sai, quem esta devendo. */
    private function administracao(Staff $staff)
    {
        $competencia = Consulta::competenciaDe();

        $doMes = Consulta::query()->where('competencia', $competencia);

        return view('paginas.painel.administracao', [
            'staff' => $staff,
            'competencia' => $competencia,
            'clientesAtivos' => Cliente::where('situacao', 'ativo')->count(),
            'inadimplentes' => Cliente::where('situacao', 'inadimplente')->count(),
            'consultas' => (clone $doMes)->count(),

            // Custo do fornecedor do mes: e a segunda maior conta da empresa e
            // so aparecia linha a linha na matriz de precos. Numero interno, e
            // por isso vive apenas neste painel.
            'custoCents' => (int) (clone $doMes)->where('situacao', Consulta::SUCESSO)->sum('custo_cents'),
            'consumoCents' => (int) (clone $doMes)->where('situacao', Consulta::SUCESSO)->sum('preco_cents'),

            'aReceber' => (int) Fatura::whereIn('situacao_pagamento', [Fatura::PAGAMENTO_PENDENTE, Fatura::PAGAMENTO_VENCIDO])->sum('total_cents'),
            'vencido' => (int) Fatura::where('situacao_pagamento', Fatura::PAGAMENTO_VENCIDO)->sum('total_cents'),

            'aCaminhoDaSuspensao' => Alertas::aCaminhoDaSuspensao(Fatura::query()),
            'comissaoPorVendedor' => $this->comissaoPorVendedor(),
        ]);
    }

    /** Minha carteira esta bem? Quem parou de usar e quem vou perder. */
    private function vendedor(Staff $staff)
    {
        $competencia = Consulta::competenciaDe();

        $daCarteira = fn () => Cliente::query()->where('vendedor_id', $staff->id);
        $faturas = fn () => Fatura::query()->where('vendedor_id', $staff->id);

        return view('paginas.painel.vendedor', [
            'staff' => $staff,
            'competencia' => $competencia,
            'clientesAtivos' => $daCarteira()->where('situacao', 'ativo')->count(),
            'consultas' => Consulta::query()
                ->whereIn('cliente_id', $daCarteira()->select('id'))
                ->where('competencia', $competencia)
                ->count(),

            // O que ele tem a receber, e nao o que a Avalia tem a receber.
            'comissaoLiberada' => (int) $faturas()->whereNotNull('comissao_liberada_em')->sum('comissao_cents'),
            'comissaoAConfirmar' => (int) $faturas()->whereNull('comissao_liberada_em')->sum('comissao_cents'),

            'aCaminhoDaSuspensao' => Alertas::aCaminhoDaSuspensao($faturas()),
            'pararamDeConsultar' => Alertas::pararamDeConsultar($daCarteira()),
        ]);
    }

    /**
     * Comissao liberada, aberta por vendedor.
     *
     * O total sozinho nao serve para pagar ninguem: o repasse e por pessoa, e
     * fechar a soma sem saber de quem e cada parte e o que faz o financeiro
     * abrir o Financeiro e somar a mao todo mes.
     *
     * @return \Illuminate\Support\Collection<int, array{nome: string, cents: int}>
     */
    private function comissaoPorVendedor(): \Illuminate\Support\Collection
    {
        $totais = Fatura::query()
            ->whereNotNull('comissao_liberada_em')
            ->whereNotNull('vendedor_id')
            ->selectRaw('vendedor_id, sum(comissao_cents) as cents')
            ->groupBy('vendedor_id')
            ->pluck('cents', 'vendedor_id');

        $nomes = Staff::whereIn('id', $totais->keys())->pluck('nome', 'id');

        return $totais
            ->map(fn ($cents, $id) => ['nome' => $nomes[$id] ?? 'Vendedor removido', 'cents' => (int) $cents])
            ->sortByDesc('cents')
            ->values();
    }
}
