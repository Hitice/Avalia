<?php

namespace App\Http\Controllers;

use App\Contabil\Competencia;
use App\Contabil\Resultado;
use App\Models\ContaFinanceira;
use App\Models\Socio;
use Illuminate\Http\Request;

/**
 * O resultado da CASA, e nao de um produto dela.
 *
 * O financeiro da sociedade morava dentro do Avalia One, que e produto e nao
 * empresa: eram tres respostas por produto e nenhuma da casa.
 *
 * Tudo sai do razao, por saldo de conta. Nenhuma linha daqui recalcula
 * documento, e e isso que faz o numero ser o mesmo em qualquer recorte.
 */
class ControladoriaController extends Controller
{
    public function __invoke(Request $pedido)
    {
        $competencia = Competencia::pedida($pedido->query('competencia'));

        $doMes = Resultado::saldos($competencia);
        $deSempre = Resultado::saldos();
        $produtos = Resultado::porProduto($competencia);

        $socios = Socio::where('ativo', true)->orderBy('id')->get();
        $lucro = array_sum(array_column($produtos, 'lucro'));

        return view('paginas.controladoria.index', [
            'competencia' => $competencia,
            'competencias' => Competencia::existentes(),
            'produtos' => $produtos,
            'lucro' => $lucro,

            // Caixa e de SEMPRE: a pergunta e quanto ha, nao quanto entrou no mes.
            'caixa' => $deSempre['caixa'] ?? 0,
            'aReceber' => $deSempre['clientes-a-receber'] ?? 0,
            'aPagarDeComissao' => $deSempre['comissao-a-pagar'] ?? 0,
            'despesa' => $doMes['despesa'] ?? 0,

            'porSocio' => $socios->map(fn (Socio $socio) => [
                'nome' => $socio->nome,
                'participacao' => $socio->participacao_bps,

                // Nao e o que ele tem a receber: distribuicao depende de decisao,
                // e nao de calculo. Dai o nome da coluna.
                'cabe' => (int) round($lucro * $socio->participacao_bps / 10000),

                'aportou' => $this->doSocio($socio, ContaFinanceira::APORTE),
                'a_devolver' => $this->doSocio($socio, ContaFinanceira::EMPRESTIMO),
            ]),

            'participacaoTotal' => $socios->sum('participacao_bps'),
        ]);
    }

    /** O saldo de uma conta pessoal do socio, que nasce sob demanda. */
    private function doSocio(Socio $socio, string $prefixo): int
    {
        return ContaFinanceira::firstWhere('codigo', $prefixo.':'.$socio->id)?->saldoCents() ?? 0;
    }
}
