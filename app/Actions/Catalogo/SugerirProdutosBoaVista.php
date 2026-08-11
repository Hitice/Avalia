<?php

namespace App\Actions\Catalogo;

use App\Models\Servico;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Preenche o produto da Boa Vista nos servicos que ainda nao tem.
 *
 * O campo liga o servico vendido ao produto contratado no fornecedor, e sem ele
 * a consulta real nao sai. Preencher dez linhas a mao, tendo que caçar o nome
 * exato de cada produto na API Reference, e o tipo de tarefa que fica por
 * fazer, e foi o que aconteceu.
 *
 * O mapa aqui e SUGESTAO, montada pelo nome comercial de cada linha contra a
 * lista de produtos do orquestrador. Ele nao substitui o contrato: produto que
 * a Avalia nao contratou volta recusado pelo fornecedor, e produto trocado
 * devolve o relatorio errado. Por isso:
 *
 * - so preenche o que esta VAZIO, nunca sobrescreve escolha de quem sabe;
 * - roda a mao, e nunca sozinha;
 * - cada linha preenchida entra na trilha, para dar para desfazer sabendo o
 *   que foi mexido.
 *
 * O par PF|PJ existe porque uma linha comercial ("Credito Net PF/PJ") vira dois
 * produtos no fornecedor, e o conector escolhe pelo documento consultado.
 */
class SugerirProdutosBoaVista
{
    /**
     * Codigo do servico => produto no fornecedor, em PF|PJ quando ha os dois.
     *
     * @var array<string, string>
     */
    public const MAPA = [
        'scpc-bvs' => 'SCPC_NET_PF|SCPC_NET_PJ',
        'credito-net' => 'SCPC_NET_PF|SCPC_NET_PJ',
        'credito-net-top' => 'ACERTA_MAIS_POSITIVO|DEFINE_NEGOCIO_POSITIVO',
        'credito-net-basica' => 'ACERTA_ESSENCIAL_POSITIVO|DEFINE_CADASTRO',
        'relatorio-plus' => 'ACERTA_MAIS_POSITIVO|DEFINE_NEGOCIO_POSITIVO',
        'prime-basica' => 'ACERTA_ESSENCIAL_POSITIVO|DEFINE_CADASTRO',
        'prime-completa' => 'ACERTA_COMPLETO_POSITIVO|DEFINE_RISCO_POSITIVO',
        'prime-completa-scr' => 'ACERTA_COMPLETO_POSITIVO|DEFINE_LIMITE_POSITIVO',
        'score-positivo' => 'SCORE_PF|SCORE_PJ',
        'relatorio-top' => 'ACERTA_COMPLETO_POSITIVO|DEFINE_RISCO_POSITIVO',
        'relatorio-top-scr' => 'ACERTA_COMPLETO_POSITIVO|DEFINE_LIMITE_POSITIVO',
        'cadastro-especial-pf' => 'ACERTA_CADASTRAL',
        'cadastro-especial-pj' => 'DEFINE_CADASTRO',
    ];

    /**
     * Quantos servicos ainda esperam o produto.
     *
     * So os que a sugestao alcanca: contar os que ela nao cobre prometeria um
     * conserto que o botao nao faz.
     */
    public static function pendentes(): int
    {
        return Servico::query()
            ->whereIn('codigo', array_keys(self::MAPA))
            ->where(fn ($q) => $q->whereNull('codigo_fornecedor')->orWhere('codigo_fornecedor', ''))
            ->count();
    }

    /** @return int quantos foram preenchidos */
    public function __invoke(): int
    {
        $servicos = Servico::query()
            ->whereIn('codigo', array_keys(self::MAPA))
            ->where(fn ($q) => $q->whereNull('codigo_fornecedor')->orWhere('codigo_fornecedor', ''))
            ->get();

        if ($servicos->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($servicos) {
            foreach ($servicos as $servico) {
                $produto = self::MAPA[$servico->codigo];

                $servico->update([
                    'codigo_fornecedor' => $produto,
                    // Quem recebe produto da Boa Vista passa a ser atendido por
                    // ela, senao o produto ficaria gravado e a consulta ainda
                    // sairia pelo conector errado.
                    'fornecedor' => $servico->fornecedor ?: 'boa-vista',
                ]);

                Auditar::registrar('servico.produto_sugerido', $servico, [
                    'servico' => $servico->codigo,
                    'produto' => $produto,
                ]);
            }
        });

        return $servicos->count();
    }
}
