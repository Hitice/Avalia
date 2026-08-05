<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Plano;
use App\Support\Dinheiro;
use App\Support\Simulacao;
use Illuminate\Http\Request;

/**
 * Simulador de lucro de um contrato.
 *
 * Responde a pergunta que se faz antes de assinar: este cliente, neste plano,
 * consumindo isto, da quanto para a Avalia e quanto para o vendedor?
 *
 * Tudo por GET e nada e gravado: simulacao nao e proposta. Assim o endereco
 * carrega o cenario inteiro e o operador manda o link para o colega conferir,
 * em vez de repetir os numeros no chat.
 */
class CalculadoraController extends Controller
{
    /** Mensalidade da Avalia, do PDD. Serve so de valor inicial do campo. */
    private const MENSALIDADE_PADRAO_CENTS = 7_990;

    public function __invoke(Request $request)
    {
        $catalogo = Catalogo::vigente();
        $faixas = $catalogo?->faixas() ?? [];

        $faixa = $this->faixaEscolhida($request, $faixas);
        $plano = $this->planoDaFaixa($faixa);

        $mensalidade = Dinheiro::paraCentavos($request->query('mensalidade'))
            ?? $plano?->mensalidade_cents
            ?? self::MENSALIDADE_PADRAO_CENTS;

        // Sem consumo informado, o cenario neutro e o cliente que consome
        // exatamente o minimo: e o que a proposta promete.
        $consumo = Dinheiro::paraCentavos($request->query('consumo')) ?? $faixa;

        // Custo estimado do catalogo, mas editavel: o mix real de consultas
        // muda de cliente para cliente e ninguem sabe qual sera.
        $custoDoCatalogo = $catalogo?->custoSobreVendaBps($faixa);
        $custoBps = $request->filled('custo_pct')
            ? (int) round((float) str_replace(',', '.', (string) $request->query('custo_pct')) * 100)
            : $custoDoCatalogo;

        $adesao = Dinheiro::paraCentavos($request->query('adesao')) ?? 0;
        $parcelas = max(1, (int) $request->query('parcelas', 1));
        $excedente = $request->boolean('excedente');

        $mes = Simulacao::mensal(
            consumoCents: $consumo,
            consumoMinimoCents: $faixa,
            mensalidadeCents: $mensalidade,
            custoSobreVendaBps: $custoBps ?? 0,
            impostoBps: $catalogo?->imposto_bps ?? 0,
            comissaoPct: \App\Support\Comissao::pct($excedente),
        );

        return view('paginas.catalogo.calculadora', [
            'catalogo' => $catalogo,
            'faixas' => $faixas,
            'faixa' => $faixa,
            'plano' => $plano,
            'mes' => $mes,
            'adesao' => Simulacao::adesaoDoMes($adesao, $parcelas),
            'entrada' => [
                'consumo' => $consumo,
                'mensalidade' => $mensalidade,
                'custo_bps' => $custoBps,
                'adesao' => $adesao,
                'parcelas' => $parcelas,
                'excedente' => $excedente,
            ],
            'custoDoCatalogo' => $custoDoCatalogo,
        ]);
    }

    /** @param  list<int>  $faixas */
    private function faixaEscolhida(Request $request, array $faixas): int
    {
        $pedida = Dinheiro::paraCentavos($request->query('faixa'));

        if ($pedida !== null && in_array($pedida, $faixas, true)) {
            return $pedida;
        }

        // Faixa do meio como padrao: comeca num cenario plausivel em vez do
        // extremo, que sempre parece bom demais ou ruim demais.
        return $faixas === [] ? 0 : $faixas[intdiv(count($faixas), 2)];
    }

    private function planoDaFaixa(int $faixa): ?Plano
    {
        return Plano::where('consumo_minimo_cents', $faixa)->where('ativo', true)->first();
    }
}
