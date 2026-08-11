<?php

namespace App\Actions\Consumo;

use App\Models\Consulta;
use App\Models\Servico;
use App\Models\Staff;
use App\Services\Conectores\EscolherConector;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * A consulta que a propria casa faz, sem cliente do outro lado.
 *
 * Sao duas situacoes com o mesmo mecanismo e regras de dinheiro diferentes:
 *
 * - Vendedor demonstrando: preco zero, e o custo real sai da comissao a receber
 *   dele. A propria consulta e o registro do desconto, entao nao ha segunda
 *   tabela para divergir da primeira. Teto diario apertado, porque demonstracao
 *   e argumento de venda.
 *
 * - Administracao consultando a trabalho: preco zero e custo da operacao. Nao
 *   ha comissao de onde descontar, entao o custo entra no custo do periodo e
 *   reduz a margem no fechamento, e nada mais.
 *
 * Nos dois casos ninguem e cobrado e o custo do fornecedor fica congelado na
 * linha: a Avalia paga o fornecedor do mesmo jeito.
 */
class ExecutarDemonstracao
{
    public const FINALIDADE = 'Demonstração comercial, pesquisa de score de crédito';

    public const FINALIDADE_OPERACAO = 'Conferência interna, pesquisa de score de crédito';

    public function __construct(private readonly EscolherConector $bureaus) {}

    /** Quantas consultas proprias esta conta ainda pode fazer hoje. */
    public static function restantes(Staff $conta): int
    {
        $hoje = Consulta::query()
            ->where('vendedor_id', $conta->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return max(0, self::teto($conta) - $hoje);
    }

    public static function teto(Staff $conta): int
    {
        return $conta->ehAdmin() ? Consulta::LIMITE_DIARIO_OPERACAO : Consulta::LIMITE_DIARIO_DEMONSTRACAO;
    }

    /** @return array{erro: string|null, consulta: Consulta|null} */
    public function __invoke(Staff $vendedor, Servico $servico, string $documento): array
    {
        $documento = preg_replace('/\D/', '', $documento) ?? '';
        $daCasa = $vendedor->ehAdmin();
        $finalidade = $daCasa ? self::FINALIDADE_OPERACAO : self::FINALIDADE;
        $teto = self::teto($vendedor);

        // A ciencia dos termos vale tambem para quem demonstra.
        if (! $vendedor->aceitouObrigatorios()) {
            return ['erro' => 'Existem termos aguardando o seu aceite.', 'consulta' => null];
        }

        if (! $servico->disponivel()) {
            return ['erro' => "O serviço {$servico->nome} não está liberado para consulta.", 'consulta' => null];
        }

        $hoje = Consulta::query()
            ->where('vendedor_id', $vendedor->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($hoje >= $teto) {
            return ['erro' => $daCasa
                ? sprintf('Limite de %d consultas por dia atingido.', $teto)
                : sprintf('Limite de %d demonstrações por dia atingido. Fale com a administração.', $teto),
                'consulta' => null];
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

        // O conector sai do SERVICO: "Base III" e Boa Vista, e outras linhas
        // vem de outro fornecedor. Escolha global mandaria para um bureau ate
        // o que ele nao vende.
        $conector = $this->bureaus->para($servico);

        $resposta = $conector->consultar($servico, $documento, $finalidade);

        $consulta = DB::transaction(fn () => Consulta::create([
            'cliente_id' => null,
            'vendedor_id' => $vendedor->id,
            'servico_id' => $servico->id,
            'competencia' => Consulta::competenciaDe(),
            'documento' => $documento,
            'finalidade' => $finalidade,
            'solicitante' => $vendedor->nome,
            'situacao' => $resposta->sucesso ? Consulta::SUCESSO : Consulta::FALHA,
            'referencia_externa' => $resposta->referenciaExterna,
            'duracao_ms' => $resposta->duracaoMs,
            'resposta' => $resposta->sucesso ? $resposta->dados : ['erro' => $resposta->erro],

            // Preco zero: ninguem e cobrado. Custo congelado: e o que sai da
            // comissao do vendedor, ou o custo que a casa assume quando quem
            // consulta e a administracao. Falha nao custa nada.
            'preco_cents' => 0,
            'custo_cents' => $resposta->sucesso ? $custo : 0,

            'expurgar_em' => now()->addDays(Consulta::DIAS_DE_RETENCAO)->toDateString(),
        ]));

        Auditar::registrar('consulta.'.$consulta->situacao, $consulta, [
            'servico' => $servico->codigo,
            'finalidade' => $finalidade,
            'origem' => $daCasa ? 'operacao' : 'demonstracao',
            'fornecedor' => $conector->nome(),
        ]);

        return $resposta->sucesso
            ? ['erro' => null, 'consulta' => $consulta]
            : ['erro' => $resposta->erro, 'consulta' => $consulta];
    }
}
