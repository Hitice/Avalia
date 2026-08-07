<?php

namespace App\Actions\Consumo;

use App\Contracts\ConectorBureau;
use App\Models\Consulta;
use App\Models\Servico;
use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * A consulta de demonstracao do vendedor.
 *
 * Ninguem e cobrado: preco zero para o cliente que nem existe ainda, e o custo
 * real congelado na linha, porque a Avalia paga o fornecedor do mesmo jeito. O
 * custo sai da comissao a receber do vendedor (regra do negocio), entao a
 * propria consulta e o registro do desconto: nao ha segunda tabela para
 * divergir da primeira.
 *
 * O teto diario e mais apertado que o do cliente: demonstracao e argumento de
 * venda, nao operacao.
 */
class ExecutarDemonstracao
{
    public const FINALIDADE = 'Demonstração comercial, pesquisa de score de crédito';

    public function __construct(private readonly ConectorBureau $conector) {}

    /** @return array{erro: string|null, consulta: Consulta|null} */
    public function __invoke(Staff $vendedor, Servico $servico, string $documento): array
    {
        $documento = preg_replace('/\D/', '', $documento) ?? '';

        if (! $servico->disponivel()) {
            return ['erro' => "O serviço {$servico->nome} não está liberado para consulta.", 'consulta' => null];
        }

        $hoje = Consulta::query()
            ->where('vendedor_id', $vendedor->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($hoje >= Consulta::LIMITE_DIARIO_DEMONSTRACAO) {
            return ['erro' => sprintf(
                'Limite de %d demonstrações por dia atingido. Fale com a administração.',
                Consulta::LIMITE_DIARIO_DEMONSTRACAO,
            ), 'consulta' => null];
        }

        // Clique repetido devolve a mesma consulta, como no portal do cliente:
        // reenvio de formulario nao pode virar custo dobrado ao fornecedor.
        $recente = Consulta::query()
            ->where('vendedor_id', $vendedor->id)
            ->where('servico_id', $servico->id)
            ->where('documento', $documento)
            ->where('situacao', Consulta::SUCESSO)
            ->where('created_at', '>=', now()->subSeconds(Consulta::SEGUNDOS_SEM_REPETIR))
            ->latest('id')
            ->first();

        if ($recente) {
            return ['erro' => null, 'consulta' => $recente];
        }

        // O custo e por servico e igual em todas as faixas: qualquer linha do
        // catalogo vigente serve para congelar o numero.
        $custo = (int) \App\Models\Catalogo::vigente()
            ?->precos()
            ->where('servico_id', $servico->id)
            ->value('custo_cents');

        $resposta = $this->conector->consultar($servico, $documento, self::FINALIDADE);

        $consulta = DB::transaction(fn () => Consulta::create([
            'cliente_id' => null,
            'vendedor_id' => $vendedor->id,
            'servico_id' => $servico->id,
            'competencia' => Consulta::competenciaDe(),
            'documento' => $documento,
            'finalidade' => self::FINALIDADE,
            'solicitante' => $vendedor->nome,
            'situacao' => $resposta->sucesso ? Consulta::SUCESSO : Consulta::FALHA,
            'referencia_externa' => $resposta->referenciaExterna,
            'duracao_ms' => $resposta->duracaoMs,
            'resposta' => $resposta->sucesso ? $resposta->dados : ['erro' => $resposta->erro],

            // Preco zero: ninguem e cobrado. Custo congelado: e o que sai da
            // comissao do vendedor, e falha nao custa nada.
            'preco_cents' => 0,
            'custo_cents' => $resposta->sucesso ? $custo : 0,

            'expurgar_em' => now()->addDays(Consulta::DIAS_DE_RETENCAO)->toDateString(),
        ]));

        Auditar::registrar('consulta.'.$consulta->situacao, $consulta, [
            'servico' => $servico->codigo,
            'finalidade' => self::FINALIDADE,
            'origem' => 'demonstracao',
            'fornecedor' => $this->conector->nome(),
        ]);

        return $resposta->sucesso
            ? ['erro' => null, 'consulta' => $consulta]
            : ['erro' => $resposta->erro, 'consulta' => $consulta];
    }
}
