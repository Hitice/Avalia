<?php

namespace App\Actions\Consumo;

use App\Contracts\ConectorBureau;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Servico;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Uma consulta, do pedido ao registro.
 *
 * Reune as tres coisas que precisam acontecer juntas ou nao acontecer:
 * conferir se a empresa pode consultar, falar com o fornecedor, e gravar o que
 * sera cobrado.
 *
 * A regra que mais importa: FALHA NAO VIRA COBRANCA. O registro fica, com o
 * motivo, porque o cliente pode perguntar por que tentou e nao veio nada, mas
 * preco e custo ficam zerados e a linha nao entra no consumo da competencia.
 * Cobrar por resposta que nao veio e o jeito mais rapido de perder um contrato.
 *
 * O preco continua sendo COPIADO do catalogo no instante da consulta, e nao
 * lido depois: e isso que permite reprecificar sem reescrever o passado.
 */
class ExecutarConsulta
{
    public function __construct(private readonly ConectorBureau $conector) {}

    /** @return array{erro: string|null, consulta: Consulta|null} */
    public function __invoke(
        Cliente $cliente,
        Servico $servico,
        string $documento,
        string $finalidade,
        ?string $solicitante = null,
    ): array {
        $documento = preg_replace('/\D/', '', $documento) ?? '';

        if (! $cliente->podeConsultar()) {
            return $this->recusa($cliente->motivoSuspensao() ?? 'Empresa sem acesso a consultas.');
        }

        if (! $cliente->plano) {
            return $this->recusa('Empresa sem plano contratado.');
        }

        if (! $cliente->documentosObrigatoriosAceitos()) {
            return $this->recusa('Existem documentos obrigatórios pendentes de aceite.');
        }

        if (! $servico->disponivel()) {
            return $this->recusa("O serviço {$servico->nome} não está liberado para consulta.");
        }

        $preco = $cliente->plano->catalogo
            ?->precos()
            ->where('servico_id', $servico->id)
            ->where('consumo_minimo_cents', $cliente->plano->consumo_minimo_cents)
            ->first();

        if (! $preco) {
            return $this->recusa("O serviço {$servico->nome} não está no plano contratado.");
        }

        $competencia = Consulta::competenciaDe();

        // Competencia fechada ja virou cobranca: aceitar mais uma linha mudaria
        // um numero que o cliente ja recebeu.
        if (Fatura::where('cliente_id', $cliente->id)->where('competencia', $competencia)->exists()) {
            return $this->recusa("O período {$competencia} já foi fechado.");
        }

        $resposta = $this->conector->consultar($servico, $documento, $finalidade);

        $consulta = DB::transaction(fn () => Consulta::create([
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'competencia' => $competencia,
            'documento' => $documento,
            'finalidade' => $finalidade,
            'solicitante' => $solicitante,
            'situacao' => $resposta->sucesso ? Consulta::SUCESSO : Consulta::FALHA,
            'referencia_externa' => $resposta->referenciaExterna,
            'duracao_ms' => $resposta->duracaoMs,
            'resposta' => $resposta->sucesso ? $resposta->dados : ['erro' => $resposta->erro],

            // O que nao respondeu nao custa e nao se cobra.
            'preco_cents' => $resposta->sucesso ? $preco->preco_cents : 0,
            'custo_cents' => $resposta->sucesso ? $preco->custo_cents : 0,

            'expurgar_em' => now()->addDays(Consulta::DIAS_DE_RETENCAO)->toDateString(),
        ]));

        Auditar::registrar('consulta.'.$consulta->situacao, $consulta, [
            'servico' => $servico->codigo,
            'finalidade' => $finalidade,
            'fornecedor' => $this->conector->nome(),
        ]);

        return $resposta->sucesso
            ? ['erro' => null, 'consulta' => $consulta]
            : ['erro' => $resposta->erro, 'consulta' => $consulta];
    }

    /** @return array{erro: string, consulta: null} */
    private function recusa(string $mensagem): array
    {
        return ['erro' => $mensagem, 'consulta' => null];
    }
}
