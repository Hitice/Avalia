<?php

namespace App\Actions\Consumo;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Servico;
use App\Services\Conectores\ConsultarFontes;
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
    public function __construct(private readonly ConsultarFontes $fontes) {}

    /** @return array{erro: string|null, consulta: Consulta|null} */
    public function __invoke(
        Cliente $cliente,
        Servico $servico,
        string $documento,
        string $finalidade,
        ?string $solicitante = null,
        ?\App\Models\Operador $operador = null,
    ): array {
        // A ciencia dos termos e por pessoa: o operador declara a dele antes
        // da primeira consulta, alem do aceite contratual da empresa.
        if ($operador && ! $operador->aceitouObrigatorios()) {
            return $this->recusa('Existem documentos aguardando o seu aceite.');
        }

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

        /*
         * Teto diario.
         *
         * Um laco mal escrito do lado do cliente, ou um acesso vazado, gera
         * milhares de consultas pagas ao fornecedor antes de alguem olhar. O
         * teto para o prejuizo no mesmo dia, e o operador libera se for uso
         * legitimo.
         */
        $hoje = Consulta::query()
            ->where('cliente_id', $cliente->id)
            ->where('situacao', Consulta::SUCESSO)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($hoje >= Consulta::LIMITE_DIARIO) {
            return $this->recusa(sprintf(
                'Limite de %d consultas por dia atingido. Fale com a Avalia para liberar mais.',
                Consulta::LIMITE_DIARIO,
            ));
        }

        $preco = $cliente->plano->catalogo
            ?->precos()
            ->where('servico_id', $servico->id)
            ->where('consumo_minimo_cents', $cliente->plano->faixaDePrecoCents())
            ->first();

        if (! $preco) {
            return $this->recusa("O serviço {$servico->nome} não está no plano contratado.");
        }

        $competencia = Consulta::competenciaDe();

        /*
         * Clique repetido nao vira cobranca repetida.
         *
         * O navegador reenvia formulario, a pessoa clica duas vezes, a conexao
         * cai e ela tenta de novo. Sem esta janela, cada uma dessas viraria uma
         * consulta paga ao fornecedor e cobrada do cliente pela mesma
         * informacao, e a reclamacao chegaria semanas depois na fatura.
         *
         * A janela e curta de proposito: consultar de novo no dia seguinte e
         * uso legitimo, porque a informacao do bureau muda.
         */
        $recente = Consulta::query()
            ->where('cliente_id', $cliente->id)
            ->where('servico_id', $servico->id)
            ->where('documento', $documento)
            ->where('situacao', Consulta::SUCESSO)
            ->where('created_at', '>=', now()->subSeconds(Consulta::SEGUNDOS_SEM_REPETIR))
            ->latest('id')
            ->first();

        if ($recente) {
            return ['erro' => null, 'consulta' => $recente];
        }

        // Competencia fechada ja virou cobranca: aceitar mais uma linha mudaria
        // um numero que o cliente ja recebeu.
        if (Fatura::where('cliente_id', $cliente->id)->where('competencia', $competencia)->exists()) {
            return $this->recusa("O período {$competencia} já foi fechado.");
        }

        // Um servico pode puxar de mais de uma base, e base e fornecedor
        // diferente. Vale o que voltou: relatorio incompleto e util, relatorio
        // que nao sai nao e util para ninguem.
        ['resposta' => $resposta, 'fontes' => $atendidas, 'indisponiveis' => $indisponiveis]
            = ($this->fontes)($servico, $documento, $finalidade);

        $dados = $resposta->dados;

        // O que faltou vai gravado NA consulta, e nao so na mensagem: quem
        // reabrir o resultado daqui a um mes precisa saber que aquele laudo
        // saiu incompleto, e por que.
        if ($resposta->sucesso && $indisponiveis !== []) {
            $dados['fontes_indisponiveis'] = $indisponiveis;
        }

        $consulta = DB::transaction(fn () => Consulta::create([
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            // Quem clicou, com nome: e a resposta de LGPD para "quem consultou
            // este documento", por pessoa e nao por empresa.
            'operador_id' => $operador?->id,
            'competencia' => $competencia,
            'documento' => $documento,
            'finalidade' => $finalidade,
            'solicitante' => $solicitante ?? $operador?->nome,
            'situacao' => $resposta->sucesso ? Consulta::SUCESSO : Consulta::FALHA,
            'referencia_externa' => $resposta->referenciaExterna,
            'duracao_ms' => $resposta->duracaoMs,
            'resposta' => $resposta->sucesso ? $dados : ['erro' => $resposta->erro],

            // O que nao respondeu nao custa e nao se cobra.
            'preco_cents' => $resposta->sucesso ? $preco->preco_cents : 0,
            'custo_cents' => $resposta->sucesso ? $preco->custo_cents : 0,

            'expurgar_em' => now()->addDays(Consulta::DIAS_DE_RETENCAO)->toDateString(),
        ]));

        Auditar::registrar('consulta.'.$consulta->situacao, $consulta, [
            'servico' => $servico->codigo,
            'finalidade' => $finalidade,
            'fornecedor' => implode(', ', $atendidas),
            'fontes_indisponiveis' => array_keys($indisponiveis),
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
