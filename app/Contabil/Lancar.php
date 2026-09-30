<?php

namespace App\Contabil;

use App\Models\LancamentoFinanceiro;
use App\Support\Auditar;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * O unico lugar que escreve no razao.
 *
 * `RegistrarLancamento` traduz natureza em pernas e delega aqui; os eventos de
 * produto montam as pernas e delegam aqui. Dois escritores e como um saldo passa
 * a depender de por onde o dinheiro entrou.
 */
final class Lancar
{
    /**
     * @param  array{natureza: string, descricao: string, competencia: string, ocorrido_em: \DateTimeInterface|string, contraparte?: string|null, documento?: string|null, comprovante?: string|null, origem_tipo?: string|null, origem_id?: int|null, staff_id?: int|null}  $dados
     */
    public function __invoke(Partidas $partidas, array $dados): LancamentoFinanceiro
    {
        return DB::transaction(function () use ($partidas, $dados) {
            $lancamento = LancamentoFinanceiro::create([
                'natureza' => $dados['natureza'],
                'descricao' => $dados['descricao'],
                'competencia' => $dados['competencia'],
                'ocorrido_em' => $dados['ocorrido_em'],
                'contraparte' => $dados['contraparte'] ?? null,
                'documento' => $dados['documento'] ?? null,
                'comprovante' => $dados['comprovante'] ?? null,

                // O par (tipo, id) e unico no banco: e o que impede a mesma
                // fatura virar receita duas vezes quando alguem reimporta.
                'origem_tipo' => $dados['origem_tipo'] ?? null,
                'origem_id' => $dados['origem_id'] ?? null,

                // Vem de fora quando nao ha sessao, como no lastro do historico,
                // que roda por comando e nao tem quem responder por ele.
                'staff_id' => $dados['staff_id'] ?? auth('staff')->id(),
            ]);

            foreach ($partidas->pernas as $contaId => $cents) {
                $lancamento->partidas()->create(['conta_id' => $contaId, 'valor_cents' => $cents]);
            }

            Auditar::registrar('socios.lancamento.registrado', $lancamento, [
                'natureza' => $dados['natureza'],
                'valor_cents' => $partidas->debitoCents(),
            ]);

            return $lancamento->load('partidas');
        });
    }

    /**
     * Lanca, ou nao faz nada se a origem ja foi lancada.
     *
     * O indice unico de origem e o arbitro, e nao uma consulta antes: consultar
     * e depois inserir deixa a janela em que duas passadas do lastro conferem
     * juntas e inserem as duas. Deixar o banco recusar fecha a janela.
     */
    public function umaVez(Partidas $partidas, array $dados): ?LancamentoFinanceiro
    {
        try {
            return $this($partidas, $dados);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'lancamento_origem_unica')
                && ! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }

            return null;
        }
    }
}
