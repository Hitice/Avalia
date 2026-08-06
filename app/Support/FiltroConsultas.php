<?php

namespace App\Support;

use App\Models\Consulta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros do painel de consultas, iguais para as tres audiencias.
 *
 * A empresa, o vendedor e a administracao olham a mesma lista com recortes
 * diferentes de quais linhas enxergam, mas as perguntas que fazem sobre ela sao
 * as mesmas: de que periodo, de que servico, deu certo ou nao. Manter isso em um
 * lugar so evita que a lista da empresa ganhe um filtro que a do vendedor nao
 * tem e que as duas comecem a divergir na contagem.
 *
 * O recorte de quem ve o que NAO mora aqui: quem chama e que aplica o `where`
 * de cliente ou de carteira, antes. Filtro que decidisse visibilidade seria um
 * parametro de URL a distancia de mostrar a consulta de outra empresa.
 *
 * Nao existe busca por documento consultado, de proposito. Filtro vira query
 * string, e query string vai para o log do servidor, para o historico do
 * navegador e para o link que alguem cola no chat. O protocolo resolve o mesmo
 * caso de uso, e sozinho nao diz nada a quem nao tem acesso ao sistema.
 */
final class FiltroConsultas
{
    /** @var array<string, string> */
    public const PERIODOS = [
        '7' => 'Últimos 7 dias',
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        'tudo' => 'Todo o período',
    ];

    /** @var array<string, string> */
    public const SITUACOES = [
        '' => 'Todas',
        Consulta::SUCESSO => 'Concluídas',
        Consulta::FALHA => 'Não concluídas',
    ];

    public const PERIODO_PADRAO = '30';

    /**
     * O que o operador escolheu, ja normalizado.
     *
     * Valor fora das opcoes conhecidas volta ao padrao em vez de virar erro: o
     * endereco da tela e feito para ser colado e editado a mao.
     *
     * @return array{periodo: string, servico: string, situacao: string, protocolo: string}
     */
    public static function escolhido(Request $pedido): array
    {
        $periodo = (string) $pedido->query('periodo', self::PERIODO_PADRAO);
        $situacao = (string) $pedido->query('situacao', '');

        return [
            'periodo' => array_key_exists($periodo, self::PERIODOS) ? $periodo : self::PERIODO_PADRAO,
            'servico' => (string) $pedido->query('servico', ''),
            'situacao' => array_key_exists($situacao, self::SITUACOES) ? $situacao : '',
            'protocolo' => trim((string) $pedido->query('protocolo', '')),
        ];
    }

    /**
     * @param  Builder<Consulta>  $consultas
     * @return Builder<Consulta>
     */
    public static function aplicar(Builder $consultas, Request $pedido): Builder
    {
        $escolha = self::escolhido($pedido);

        if ($escolha['periodo'] !== 'tudo') {
            $consultas->where('created_at', '>=', now()->subDays((int) $escolha['periodo'])->startOfDay());
        }

        if ($escolha['servico'] !== '') {
            $consultas->where('servico_id', (int) $escolha['servico']);
        }

        if ($escolha['situacao'] !== '') {
            $consultas->where('situacao', $escolha['situacao']);
        }

        if ($escolha['protocolo'] !== '') {
            $consultas->where('referencia_externa', $escolha['protocolo']);
        }

        return $consultas;
    }

    /**
     * Resumo do recorte, para o cabecalho da tela.
     *
     * Conta tentativas e cobranca separadas: consulta que falhou aparece na
     * lista mas nao entra no valor, e sem essa distincao o cliente soma a tela e
     * discute a fatura.
     *
     * @param  Builder<Consulta>  $consultas  ja filtrado
     * @return array{total: int, concluidas: int, falhas: int, valor_cents: int}
     */
    public static function resumo(Builder $consultas): array
    {
        $linha = (clone $consultas)
            ->reorder()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when situacao = ? then 1 else 0 end) as concluidas', [Consulta::SUCESSO])
            ->selectRaw('coalesce(sum(preco_cents), 0) as valor_cents')
            ->first();

        $total = (int) ($linha->total ?? 0);
        $concluidas = (int) ($linha->concluidas ?? 0);

        return [
            'total' => $total,
            'concluidas' => $concluidas,
            'falhas' => $total - $concluidas,
            'valor_cents' => (int) ($linha->valor_cents ?? 0),
        ];
    }
}
