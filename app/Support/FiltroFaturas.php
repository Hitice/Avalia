<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Fatura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros da tela do financeiro.
 *
 * Irmao do FiltroConsultas e do FiltroClientes, e pelo mesmo motivo: as
 * perguntas do fechamento sao sempre as mesmas (de quem e, de que mes, paga ou
 * nao, de qual vendedor), e mante-las num lugar so evita que a tela e a
 * planilha comecem a divergir na soma.
 *
 * A busca aceita razao social ou CNPJ do contratante, que e dado cadastral da
 * propria carteira. CPF de responsavel fica de fora: nao precisa ir para a
 * barra de enderecos para achar uma fatura.
 */
final class FiltroFaturas
{
    /**
     * O que o operador escolheu, ja normalizado.
     *
     * Valor fora das opcoes conhecidas volta ao padrao em vez de virar erro: o
     * endereco da tela e feito para ser colado e editado a mao.
     *
     * @return array{busca: string, situacao: string, vendedor: string, competencia: string}
     */
    public static function escolhido(Request $pedido): array
    {
        $situacao = (string) $pedido->query('situacao', '');

        return [
            'busca' => trim((string) $pedido->query('busca', '')),
            'situacao' => in_array($situacao, Fatura::SITUACOES_PAGAMENTO, true) ? $situacao : '',
            'vendedor' => (string) $pedido->query('vendedor', ''),
            // AAAA-MM, como a competencia e guardada.
            'competencia' => preg_match('/^\d{4}-\d{2}$/', (string) $pedido->query('competencia', ''))
                ? (string) $pedido->query('competencia')
                : '',
        ];
    }

    /**
     * @param  Builder<Fatura>  $faturas
     * @return Builder<Fatura>
     */
    public static function aplicar(Builder $faturas, Request $pedido): Builder
    {
        $escolha = self::escolhido($pedido);

        if ($escolha['busca'] !== '') {
            $termo = '%'.$escolha['busca'].'%';
            $digitos = preg_replace('/\D/', '', $escolha['busca']) ?: '';

            $faturas->whereIn('cliente_id', Cliente::query()
                ->where(function (Builder $q) use ($termo, $digitos) {
                    $q->where('razao_social', 'like', $termo);

                    if ($digitos !== '') {
                        $q->orWhere('cnpj', 'like', '%'.$digitos.'%');
                    }
                })
                ->select('id'));
        }

        if ($escolha['situacao'] !== '') {
            $faturas->where('situacao_pagamento', $escolha['situacao']);
        }

        if ($escolha['vendedor'] !== '') {
            $faturas->where('vendedor_id', (int) $escolha['vendedor']);
        }

        if ($escolha['competencia'] !== '') {
            $faturas->where('competencia', $escolha['competencia']);
        }

        return $faturas;
    }

    /**
     * O que o recorte soma, para o rodape da tabela.
     *
     * Quantas e quanto, separando o que ja entrou do que ainda falta: uma soma
     * unica misturaria dinheiro recebido com dinheiro esperado, e sao as duas
     * perguntas que o fechamento faz.
     *
     * @param  Builder<Fatura>  $faturas  ja filtrado
     * @return array{quantidade: int, total_cents: int, aberto_cents: int, liquidado_cents: int}
     */
    public static function resumo(Builder $faturas): array
    {
        $linha = (clone $faturas)
            ->reorder()
            ->selectRaw('count(*) as quantidade')
            ->selectRaw('coalesce(sum(total_cents), 0) as total_cents')
            ->selectRaw('coalesce(sum(case when situacao_pagamento = ? then total_cents else 0 end), 0) as liquidado_cents', [Fatura::PAGAMENTO_LIQUIDADO])
            ->first();

        $total = (int) ($linha->total_cents ?? 0);
        $liquidado = (int) ($linha->liquidado_cents ?? 0);

        return [
            'quantidade' => (int) ($linha->quantidade ?? 0),
            'total_cents' => $total,
            'liquidado_cents' => $liquidado,
            'aberto_cents' => $total - $liquidado,
        ];
    }
}
